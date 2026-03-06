import axios, { AxiosInstance } from 'axios';
import { Config } from './config';

export class WordPressClient {
  private api: AxiosInstance;
  private siteUrl: string;

  constructor(config: Config['wp']) {
    this.siteUrl = config.url.replace(/\/$/, '');
    const auth = Buffer.from(`${config.username}:${config.password.replace(/\s+/g, '')}`).toString('base64');
    this.api = axios.create({
      baseURL: `${this.siteUrl}/wp-json`,
      headers: {
        Authorization: `Basic ${auth}`,
        'Content-Type': 'application/json',
      },
    });
  }

  getBaseUrl() {
    return this.siteUrl;
  }

  setHeaders(headers: Record<string, string>) {
    console.log('[WordPress] Updating API headers (Browser Session)');
    Object.keys(headers).forEach(key => {
      this.api.defaults.headers.common[key] = headers[key];
    });
    // If we have browser headers, we might want to remove Basic Auth to avoid conflicts
    if (headers['Cookie']) {
      delete this.api.defaults.headers.common['Authorization'];
    }
  }

  async validateAuth() {
    console.log('[WordPress] Validating authentication...');
    try {
      const response = await this.api.get('/wp/v2/users/me');
      console.log(`[WordPress] Auth successful. Logged in as: ${response.data.name} (@${response.data.slug})`);
      return true;
    } catch (error: any) {
      if (error.response) {
        console.error(`[WordPress] Auth failed (${error.response.status}):`, JSON.stringify(error.response.data, null, 2));
      } else {
        console.error(`[WordPress] Auth failed: ${error.message}`);
      }
      
      if (error.response?.status === 401) {
        console.error('------------------------------------------------------------');
        console.error('CRITICAL: AUTHENTICATION FAILED');
        console.error('The REST API requires an "Application Password".');
        console.error('Your regular login password will NOT work.');
        console.error('1. Log in to your WP Admin.');
        console.error('2. Go to Users > Profile.');
        console.error('3. Scroll to "Application Passwords".');
        console.error('4. Create a new one (e.g., name it "AI Orchestrator").');
        console.error('5. Use the 24-character code (xxxx xxxx xxxx xxxx) in .env');
        console.error('------------------------------------------------------------');
      }
      return false;
    }
  }

  async getPosts(params: any = {}) {
    console.log('[WordPress] Fetching posts...');
    const response = await this.api.get('/wp/v2/posts', { params });
    return response.data;
  }

  async getPages(params: any = {}) {
    console.log('[WordPress] Fetching pages...');
    const response = await this.api.get('/wp/v2/pages', { params });
    return response.data;
  }

  async createPost(data: any) {
    console.log('[WordPress] Creating post:', data.title);
    const response = await this.api.post('/wp/v2/posts', data);
    return response.data;
  }

  async updatePost(id: number, data: any) {
    console.log('[WordPress] Updating post:', id);
    const response = await this.api.post(`/wp/v2/posts/${id}`, data);
    return response.data;
  }

  async getGlobalStyles() {
    console.log('[WordPress] Fetching global styles...');
    const response = await this.api.get('/wp/v2/global-styles');
    return response.data;
  }

  async updateGlobalStyles(id: number, data: any) {
    console.log('[WordPress] Updating global styles:', id);
    const response = await this.api.post(`/wp/v2/global-styles/${id}`, data);
    return response.data;
  }

  async getPatterns() {
    console.log('[WordPress] Fetching block patterns...');
    const response = await this.api.get('/wp/v2/block-patterns');
    return response.data;
  }

  async getSettings() {
    console.log('[WordPress] Fetching settings...');
    const response = await this.api.get('/wp/v2/settings');
    return response.data;
  }

  async updateSettings(data: any) {
    console.log('[WordPress] Updating settings:', Object.keys(data));
    const response = await this.api.post('/wp/v2/settings', data);
    return response.data;
  }

  async createSessionLog(data: { title: string; content: string; meta?: any }) {
    try {
      // Use a unique, hidden post type or status if possible, 
      // but to ensure it's NEVER in the blog feed, we use status: 'trash' or a custom status.
      // Better yet: we keep using posts but with a status that Kadence ignores by default.
      const response = await this.api.post('/wp/v2/posts', {
        title: `AI_SESSION: ${data.title}`,
        content: data.content,
        status: 'pending', // 'pending' is hidden from almost all theme front-ends
        excerpt: JSON.stringify(data.meta || {})
      });
      return response.data;
    } catch (error: any) {
      throw error;
    }
  }

  async getSessionLogs() {
    console.log('[WordPress] Fetching session logs...');
    const response = await this.api.get('/wp/v2/posts', {
      params: { 
        per_page: 20, 
        status: 'pending', 
        search: 'AI_SESSION:',
        orderby: 'date', 
        order: 'desc' 
      }
    });
    return response.data;
  }
}
