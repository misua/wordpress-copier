import express from 'express';
import cors from 'cors';
import path from 'path';
import { getConfig } from './config';
import { DeepSeekClient } from './deepseek-client';
import { WordPressClient } from './wordpress-client';
import { Orchestrator } from './orchestrator';
import { WordPressBrowser } from './browser';

process.on('unhandledRejection', (reason, promise) => {
  console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

process.on('uncaughtException', (err) => {
  console.error('Uncaught Exception thrown:', err);
});

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../public')));

const config = getConfig();
const wpClient = new WordPressClient(config.wp);
const wpBrowser = new WordPressBrowser(config.wp);
const aiClient = new DeepSeekClient(config.deepseek);
const orchestrator = new Orchestrator(aiClient, wpClient);

// Initialize Browser Session on startup
async function initializeSession() {
  try {
    const success = await wpBrowser.login();
    if (success) {
      const headers = await wpBrowser.getAuthHeaders();
      wpClient.setHeaders(headers as any);
      console.log('[System] Linked browser session to API client.');
    }
  } catch (error) {
    console.error('[System] Failed to initialize browser session:', error);
  }
}

initializeSession();

app.post('/api/plan', async (req, res) => {
  console.log('\n[API] Received Plan Request');
  try {
    const { prompt } = req.body;
    console.log(`[API] Prompt: "${prompt}"`);
    if (!prompt) {
      console.warn('[API] Missing prompt in request body');
      return res.status(400).json({ error: 'Prompt is required' });
    }
    
    console.log('[API] Generating plan...');
    const plan = await orchestrator.plan(prompt);
    console.log('[API] Plan generated successfully:', plan.explanation);
    res.json(plan);
  } catch (error: any) {
    console.error('[API] Error in /api/plan:', error.stack || error.message);
    res.status(500).json({ error: error.message });
  }
});

app.post('/api/execute', async (req, res) => {
  console.log('\n[API] Received Execute Request');
  try {
    const { plan } = req.body;
    if (!plan) {
      console.warn('[API] Missing plan in request body');
      return res.status(400).json({ error: 'Plan is required' });
    }
    
    console.log('[API] Executing plan...');
    const result = await orchestrator.execute(plan);
    console.log('[API] Plan execution completed');
    res.json({ 
      message: 'Plan staged as drafts. Please preview before publishing.',
      results: result?.results || []
    });
  } catch (error: any) {
    console.error('[API] Error in /api/execute:', error.stack || error.message);
    res.status(500).json({ error: error.message });
  }
});

app.post('/api/publish', async (req, res) => {
  try {
    const { sessionId } = req.body;
    await orchestrator.publish(sessionId);
    res.json({ message: 'Changes published successfully!' });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

app.post('/api/undo', async (req, res) => {
  try {
    const sessionId = req.body?.sessionId;
    await orchestrator.undo(sessionId);
    res.json({ message: 'Undo successful' });
  } catch (error: any) {
    console.error('[API] Error in /api/undo:', error.message);
    res.status(500).json({ error: error.message });
  }
});

app.get('/api/sessions', async (req, res) => {
  try {
    const sessions = await orchestrator.getSessions();
    res.json(sessions);
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
  console.log(`AI WordPress Orchestrator Server running on http://localhost:${PORT}`);
});
