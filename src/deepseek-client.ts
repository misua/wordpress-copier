import OpenAI from 'openai';
import { Config } from './config';

export class DeepSeekClient {
  private openai: OpenAI;
  private model: string;

  constructor(config: Config['deepseek']) {
    this.openai = new OpenAI({
      apiKey: config.apiKey,
      baseURL: config.baseUrl,
    });
    this.model = config.model;
  }

  async createPlan(prompt: string, systemPrompt: string) {
    console.log('[DeepSeek] Sending request to API...');
    try {
      const response = await this.openai.chat.completions.create({
        model: this.model,
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: prompt },
        ],
        response_format: { type: 'json_object' },
        max_tokens: 4096,
      });

      console.log('[DeepSeek] Received response from API');
      const content = response.choices[0].message.content || '{}';
      try {
        return JSON.parse(content);
      } catch (e) {
        console.error('[DeepSeek] JSON Parse Error. Raw content length:', content.length);
        console.error('[DeepSeek] First 100 chars:', content.substring(0, 100));
        console.error('[DeepSeek] Last 100 chars:', content.substring(content.length - 100));
        throw e;
      }
    } catch (error: any) {
      console.error('[DeepSeek] API Error:', error.message);
      throw error;
    }
  }
}
