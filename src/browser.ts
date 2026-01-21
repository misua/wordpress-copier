import { chromium, Browser, Page } from 'playwright';
import { Camoufox } from 'camoufox-js';
import { Config } from './config';

export class WordPressBrowser {
  private browser: Browser | null = null;
  private page: Page | null = null;

  constructor(private config: Config['wp']) {}

  async init() {
    console.log('[Browser] Initializing Camoufox...');
    // Camoufox is a function that returns a browser or context
    this.browser = await Camoufox({
      headless: true
    }) as any;
    
    if (this.browser) {
      const context = await this.browser.newContext();
      this.page = await context.newPage();
    }
  }

  async login() {
    if (!this.page) await this.init();
    const loginUrl = `${this.config.url.replace(/\/$/, '')}/wp-login.php`;
    
    console.log(`[Browser] Navigating to ${loginUrl}...`);
    await this.page!.goto(loginUrl, { waitUntil: 'networkidle' });

    console.log(`[Browser] Attempting login for ${this.config.username}...`);
    await this.page!.waitForSelector('#user_login');
    
    // Wait for WP's wp_attempt_focus script to run
    await this.page!.waitForTimeout(500);

    await this.page!.fill('#user_login', this.config.username);
    await this.page!.fill('#user_pass', this.config.password);
    
    // Submit via JS to bypass any click interceptors
    await this.page!.evaluate(() => {
      const form = (globalThis as any).document.querySelector('#loginform');
      if (form) form.submit();
    });

    try {
      await this.page!.waitForNavigation({ waitUntil: 'load', timeout: 15000 });
    } catch (e) {
      // ignore
    }

    if (this.page!.url().includes('wp-admin')) {
      console.log('[Browser] Login successful.');
      return true;
    } else {
      console.error('[Browser] Login failed. Still on login page.');
      const errorText = await this.page!.locator('#login_error').innerText().catch(() => 'No error message found');
      console.error(`[Browser] WordPress Error: ${errorText}`);
      await this.page!.screenshot({ path: 'login-failure.png' });
      return false;
    }
  }

  async getAuthHeaders() {
    if (!this.page) return {};
    
    console.log('[Browser] Navigating to admin to refresh nonce...');
    await this.page.goto(`${this.config.url.replace(/\/$/, '')}/wp-admin/`, { waitUntil: 'networkidle' });

    // We can extract the nonce from the page if needed
    const nonce = await this.page.evaluate(() => {
      const win = globalThis as any;
      // Standard WP location for REST nonce
      const nonceFromSettings = win.wpApiSettings?.nonce;
      const nonceFromApiFetch = win.wp?.apiFetch?.nonce;
      
      if (nonceFromSettings) return nonceFromSettings;
      if (nonceFromApiFetch) return nonceFromApiFetch;

      // Fallback: look for nonce in script tags if they aren't parsed into win
      const scripts = Array.from((globalThis as any).document.querySelectorAll('script'));
      for (const script of scripts) {
        const text = (script as any).textContent || '';
        const match = text.match(/"nonce":"([a-z0-9]+)"/);
        if (match) return match[1];
      }

      return '';
    });

    console.log(`[Browser] Extracted Nonce: ${nonce ? 'FOUND' : 'NOT FOUND'}`);

    // In a browser context, cookies are sent automatically
    // But for axios, we might need to extract them
    const cookies = await this.page.context().cookies();
    const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

    return {
      'Cookie': cookieString,
      'X-WP-Nonce': nonce
    };
  }

  async close() {
    if (this.browser) await this.browser.close();
  }
}
