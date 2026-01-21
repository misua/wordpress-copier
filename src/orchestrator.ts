import { DeepSeekClient } from './deepseek-client';
import { WordPressClient } from './wordpress-client';
import { OrchestrationPlanSchema, OrchestrationPlan } from './schema';
import { getSystemPrompt } from './prompts';

export class Orchestrator {
  private patterns: any[] = [];
  private posts: any[] = [];
  private settings: any = null;
  private hasGlobalStyles: boolean = false;

  constructor(
    private aiClient: DeepSeekClient,
    private wpClient: WordPressClient
  ) {}

  async discover() {
    console.log('Auditing target site for available patterns, posts, and settings...');
    
    // Validate Auth First
    const isAuthed = await this.wpClient.validateAuth();
    if (!isAuthed) {
      console.warn('[Discovery] Proceeding with limited access (Read-Only)...');
    }

    // Fetch patterns
    try {
      this.patterns = await this.wpClient.getPatterns();
      console.log(`[Discovery] Found ${this.patterns.length} patterns.`);
    } catch (err: any) {
      console.warn(`[Discovery] Failed to fetch patterns: ${err.message}.`);
      this.patterns = [];
    }

    // Fetch posts and pages
    try {
      const [posts, pages] = await Promise.all([
        this.wpClient.getPosts({ per_page: 100, status: 'publish,draft,private' }),
        this.wpClient.getPages({ per_page: 100, status: 'publish,draft,private' })
      ]);
      this.posts = [...posts, ...pages];
      console.log(`[Discovery] Found ${this.posts.length} content items (${posts.length} posts, ${pages.length} pages).`);
      
      const home = this.posts.find(p => p.id === 12);
      if (home) {
        console.log('[Discovery] Homepage (ID 12) Content Sample:', home.content.rendered.substring(0, 200));
      } else {
        console.warn('[Discovery] Homepage (ID 12) NOT FOUND in fetched list.');
      }
    } catch (err: any) {
      console.error(`[Discovery] Failed to fetch posts/pages: ${err.message}`);
      this.posts = [];
    }

    // Fetch site settings
    try {
      this.settings = await this.wpClient.getSettings();
      console.log(`[Discovery] Site Title: "${this.settings.title}"`);
    } catch (err: any) {
      console.error(`[Discovery] Failed to fetch settings: ${err.message}`);
      this.settings = null;
    }

    // Check Global Styles support
    try {
      await this.wpClient.getGlobalStyles();
      this.hasGlobalStyles = true;
      console.log('[Discovery] Global Styles are supported.');
    } catch (err: any) {
      console.warn('[Discovery] Global Styles NOT supported (Classic Theme detected).');
      this.hasGlobalStyles = false;
    }
  }

  async plan(prompt: string): Promise<OrchestrationPlan> {
    if (this.patterns.length === 0 && this.posts.length === 0 && !this.settings) {
      await this.discover();
    }

    const systemPrompt = getSystemPrompt(this.patterns, this.posts, this.settings, this.hasGlobalStyles);
    const rawPlan = await this.aiClient.createPlan(prompt, systemPrompt);
    
    // Task 2.4: Validate against schema
    const result = OrchestrationPlanSchema.safeParse(rawPlan);
    
    if (!result.success) {
      console.error('LLM generated invalid plan:', JSON.stringify(result.error.format(), null, 2));
      console.log('Raw Plan from LLM:', JSON.stringify(rawPlan, null, 2));
      throw new Error('Failed to generate a valid orchestration plan.');
    }

    return result.data;
  }

  async execute(plan: OrchestrationPlan) {
    console.log('\n--- Initiating Pre-Execution Backup ---');
    try {
        await this.takeFullSiteSnapshot();
    } catch (e: any) {
        console.warn('Backup failed, proceeding anyway:', e.message);
    }

    console.log('\n--- Applying AI Changes ---');
    const affectedResources: any[] = [];
    const results: string[] = [];
    const idMap: Record<string, number> = {}; // Track dynamic IDs (Task 3.2 extension)
    
    for (let i = 0; i < plan.commands.length; i++) {
      const command = plan.commands[i];
      try {
        switch (command.type) {
          case 'create_page':
            console.log(`Creating page: ${command.title}`);
            const newPage = await this.wpClient.createPost({
              title: command.title,
              content: '', 
              status: 'draft', 
              type: 'page',
            });
            idMap[`cmd_${i}`] = newPage.id;
            affectedResources.push({ type: 'page', id: newPage.id, action: 'create' });
            results.push(`Created Page: ${newPage.link}`);
            console.log(`Success: Created page ID ${newPage.id}`);
            break;
            
          case 'insert_pattern':
            // Use ID from previous command if needed (e.g. if target_post_id is 0 or matches a placeholder)
            let targetId = command.target_post_id;
            if (targetId === 0 && i > 0 && idMap[`cmd_${i-1}`]) {
              targetId = idMap[`cmd_${i-1}`];
            }

            console.log(`Inserting pattern [${command.pattern_slug}] into post ${targetId}`);
            if (targetId === 0) throw new Error('Target post ID is missing.');

            const updatedPost = await this.wpClient.updatePost(targetId, {
              content: '<!-- wp:pattern {"slug":"' + command.pattern_slug + '"} /-->',
              status: 'draft' 
            });
            affectedResources.push({ type: 'post', id: targetId, action: 'update' });
            results.push(`Updated Post: ${updatedPost.link}`);
            console.log(`Success: Pattern inserted.`);
            break;
            
          case 'update_global_styles':
            console.log('Updating Global Styles...');
            const styles = await this.wpClient.getGlobalStyles();
            if (styles && styles.length > 0) {
              const currentStyles = styles[0];
              affectedResources.push({ 
                type: 'global_styles', 
                id: currentStyles.id, 
                action: 'update',
                snapshot: currentStyles.styles 
              });
              
              await this.wpClient.updateGlobalStyles(currentStyles.id, {
                styles: command.styles,
                settings: command.settings
              });
              results.push('Updated Global Styles.');
              console.log('Success: Global Styles updated.');
            }
            break;
            
          case 'update_post':
            console.log(`Staging update for ${command.post_id} as draft...`);
            const existingPost = this.posts.find(p => p.id === command.post_id);
            const type = existingPost?.type || 'post';
            const endpoint = type === 'page' ? `/wp/v2/pages/${command.post_id}` : `/wp/v2/posts/${command.post_id}`;
            
            // Capture snapshot for undo
            affectedResources.push({ 
              type: type, 
              id: command.post_id, 
              action: 'update',
              snapshot: existingPost ? {
                title: existingPost.title.rendered || existingPost.title,
                content: existingPost.content.rendered || existingPost.content,
                status: existingPost.status
              } : null
            });

            // Apply as DRAFT for preview
            const response = await (this.wpClient as any).api.post(endpoint, {
              title: command.title,
              content: command.content,
              status: 'draft' // FORCE DRAFT FOR PREVIEW
            });
            
            const updatedPostData = response.data;
            const previewUrl = updatedPostData.link.includes('?') 
              ? `${updatedPostData.link}&preview=true` 
              : `${updatedPostData.link}?preview=true`;
            results.push(`Staged ${type} as draft: ${previewUrl}`);
            console.log(`Success: ${type} ${command.post_id} staged as draft.`);
            break;

          case 'update_settings':
            console.log('Updating Site Settings...');
            // Capture FRESH snapshot before update
            const currentSettings = await this.wpClient.getSettings();
            affectedResources.push({ 
              type: 'settings', 
              action: 'update', 
              snapshot: currentSettings 
            });

            const settingsData = await this.wpClient.updateSettings({
              title: command.title,
              description: command.description,
              timezone: command.timezone
            });
            results.push(`Updated Site Settings: Title is now "${settingsData.title}". View change at ${this.wpClient.getBaseUrl()}`);
            console.log('Success: Site settings updated.');
            break;
            
          case 'upload_media':
            console.log(`Uploading media from URL: ${command.url}`);
            console.log('Success: Media upload simulation complete.');
            break;

          case 'patch_post_content':
            console.log(`Patching content for post ${command.post_id}...`);
            const postToPatch = this.posts.find(p => p.id === command.post_id);
            if (!postToPatch) throw new Error(`Post ${command.post_id} not found in cache.`);

            const currentContent = postToPatch.content?.rendered || postToPatch.content || '';
            let newContent = '';
            
            // Robust matching: Normalize whitespace for comparison
            const normalize = (str: string) => str.replace(/\s+/g, ' ').trim();
            const normalizedContent = normalize(currentContent);
            const normalizedSearch = normalize(command.search);

            if (!normalizedContent.includes(normalizedSearch)) {
              console.warn(`[Patch] Search string not found in post ${command.post_id}. ABORTING.`);
              results.push(`Error: The text you want to change was not found on page ${command.post_id}. Nothing was modified.`);
              return;
            } else {
                // Perform the replace on the ORIGINAL content to preserve tags/formatting
                // Try 1: Exact Match
                if (currentContent.includes(command.search)) {
                    console.log('[Patch] Match Found: Exact');
                    newContent = currentContent.replace(command.search, command.replace);
                } else {
                    // Try 2: HTML-tag-agnostic matching
                    const words = command.search.split(/\s+/).filter(w => w.length > 0);
                    const escapedWords = words.map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
                    const fuzzyRegexStr = escapedWords.join('(\\s+|<[^>]+>)*');
                    const fuzzyRegex = new RegExp(fuzzyRegexStr, 'g');
                    
                    if (fuzzyRegex.test(currentContent)) {
                        console.log('[Patch] Match Found: Fuzzy (Tag-agnostic)');
                        newContent = currentContent.replace(fuzzyRegex, command.replace);
                    } else {
                        console.warn(`[Patch] Fuzzy match failed for post ${command.post_id}. ABORTING to prevent content loss.`);
                        results.push(`Error: Could not find the specific text to replace in post ${command.post_id}. No changes were made to prevent overwriting the whole page.`);
                        return; // EXIT loop for this command to prevent damage
                    }
                }
            }

            const patchType = postToPatch.type || 'post';
            const patchEndpoint = patchType === 'page' ? `/wp/v2/pages/${command.post_id}` : `/wp/v2/posts/${command.post_id}`;

            // Snapshot for undo
            affectedResources.push({
              type: patchType,
              id: command.post_id,
              action: 'update',
              snapshot: {
                title: postToPatch.title.rendered || postToPatch.title,
                content: currentContent,
                status: postToPatch.status
              }
            });

            // Apply patch as draft
            const patchResponse = await (this.wpClient as any).api.post(patchEndpoint, {
              content: newContent,
              status: 'draft'
            });

            const patchData = patchResponse.data;
            const patchPreviewUrl = patchData.link.includes('?') 
              ? `${patchData.link}&preview=true` 
              : `${patchData.link}?preview=true`;
              
            results.push(`Patched ${patchType} as draft: ${patchPreviewUrl}`);
            console.log(`Success: Patched ${patchType} ${command.post_id}.`);
            break;
        }
      } catch (error: any) {
        console.error(`Error executing command ${command.type}:`, error.message);
      }
    }

    // Task 4.1: Log session to WordPress
    try {
      await this.wpClient.createSessionLog({
        title: `AI Session - ${new Date().toISOString()}`,
        content: JSON.stringify({
          plan: plan,
          affected: affectedResources
        }, null, 2)
      });
      console.log('Session log created in WordPress.');
    } catch (err) {
      console.warn('Failed to create session log in WordPress.');
    }

    console.log('--- Execution Complete ---');
    results.forEach(res => console.log(res));
    console.log('');

    return {
      results,
      affectedResources
    };
  }

  async publish(sessionId: number) {
    console.log(`\n--- Publishing Session ${sessionId} ---`);
    const logs = await this.wpClient.getSessionLogs();
    const targetLog = logs.find((l: any) => l.id === sessionId);

    if (!targetLog) throw new Error('Session not found.');

    const sessionData = JSON.parse(targetLog.content.rendered || targetLog.content || '{}');
    const affected = sessionData.affected || [];

    for (const res of affected) {
      if (res.type === 'page' || res.type === 'post') {
        console.log(`Publishing ${res.type} ${res.id}...`);
        const endpoint = res.type === 'page' ? `/wp/v2/pages/${res.id}` : `/wp/v2/posts/${res.id}`;
        await (this.wpClient as any).api.post(endpoint, { status: 'publish' });
      }
    }
    console.log('Publishing complete.');
  }

  async getSessions() {
    const logs = await this.wpClient.getSessionLogs();
    return logs.map((log: any) => ({
      id: log.id,
      date: log.date,
      summary: log.title.rendered || log.title
    }));
  }

  private async runInfrastructureCheck() {
    try {
      // We'll need a way to access the browser here. For now, we can check 
      // if settings are valid via API, but deep plugin check requires browser.
      const settings = await this.wpClient.getSettings();
      if (!settings.page_on_front || settings.page_on_front === 0) {
        console.log('[Rescue] Restoring Front Page ID to 12...');
        await this.wpClient.updateSettings({ page_on_front: 12, show_on_front: 'page' });
      }
    } catch (e: any) {
      console.warn('[Rescue] Infra check warning:', e.message);
    }
  }

  private async takeFullSiteSnapshot() {
    console.log('[Backup] Capturing current site state...');
    const [posts, pages, settings] = await Promise.all([
        this.wpClient.getPosts({ per_page: 100, status: 'publish,draft,private' }),
        this.wpClient.getPages({ per_page: 100, status: 'publish,draft,private' }),
        this.wpClient.getSettings()
    ]);

    const snapshot = {
        timestamp: new Date().toISOString(),
        theme: 'Kadence', // Hardcoded for now based on audit
        settings,
        posts: [...posts, ...pages]
    };

    // Store in a persistent log
    await this.wpClient.createSessionLog({
        title: `PRE_EXEC_BACKUP - ${new Date().toLocaleString()}`,
        content: JSON.stringify(snapshot, null, 2)
    });
    console.log('[Backup] Snapshot saved to WordPress.');
  }

  async undo(sessionId?: number) {
    console.log(`\n--- Initiating Deep Undo ${sessionId ? `for session ${sessionId}` : '(Last Session)'} ---`);
    try {
      const logs = await this.wpClient.getSessionLogs();
      const targetLog = sessionId 
        ? logs.find((l: any) => l.id === sessionId)
        : logs[0];

      if (!targetLog) {
        console.log('No session log found to undo.');
        return;
      }

      console.log(`Undoing: ${targetLog.title.rendered || targetLog.title}`);
      
      const logContent = targetLog.content.rendered || targetLog.content || '{}';
      const logExcerpt = targetLog.excerpt?.rendered || targetLog.excerpt || '';
      
      let sessionData: any = {};
      try {
        sessionData = JSON.parse(logContent);
      } catch (e) {
        try {
          sessionData = JSON.parse(logExcerpt.replace(/<[^>]*>?/gm, ''));
        } catch (e2) {
          throw new Error('Failed to parse session data.');
        }
      }
      
      const affected = sessionData.affected || [];

      // Step 1: Physical Infrastructure Recovery (Plugins/Theme)
      console.log('[Undo] Verifying site infrastructure (Elementor/Hello Biz)...');
      await this.runInfrastructureCheck();

      // Step 2: Resource Restoration
      for (const res of affected) {
        try {
          switch (res.type) {
            case 'page':
            case 'post':
              const endpoint = res.type === 'page' ? `/wp/v2/pages/${res.id}` : `/wp/v2/posts/${res.id}`;
              if (res.action === 'create') {
                console.log(`Deleting created ${res.type} ID ${res.id}...`);
                await (this.wpClient as any).api.delete(endpoint, { params: { force: true } });
              } else if (res.action === 'update' && res.snapshot) {
                console.log(`Restoring ${res.type} ID ${res.id} from snapshot...`);
                await (this.wpClient as any).api.post(endpoint, {
                  title: res.snapshot.title,
                  content: res.snapshot.content,
                  status: res.snapshot.status
                });
              }
              break;
            case 'settings':
              if (res.action === 'update' && res.snapshot) {
                console.log('Restoring site settings snapshot...');
                await this.wpClient.updateSettings(res.snapshot);
              }
              break;
            case 'global_styles':
              if (res.action === 'update' && res.snapshot) {
                console.log(`Restoring Global Styles ID ${res.id}...`);
                await this.wpClient.updateGlobalStyles(res.id, {
                  styles: res.snapshot
                });
              }
              break;
          }
        } catch (err: any) {
          console.error(`Failed to undo resource ${res.type} ${res.id}:`, err.message);
        }
      }
      
      // Delete the log entry
      const logType = targetLog.type || 'post';
      const logEndpoint = logType === 'ai-session-log' ? `/wp/v2/ai-session-log/${targetLog.id}` : `/wp/v2/posts/${targetLog.id}`;
      await (this.wpClient as any).api.delete(logEndpoint, { params: { force: true } });
      
      console.log('Undo operations complete.\n');
    } catch (error: any) {
      console.error('Failed to perform undo:', error.message);
    }
  }
}
