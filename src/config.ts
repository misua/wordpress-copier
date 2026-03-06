import dotenv from 'dotenv';
import { z } from 'zod';

dotenv.config();

const configSchema = z.object({
  wp: z.object({
    url: z.string().url(),
    username: z.string(),
    password: z.string(),
  }),
  deepseek: z.object({
    apiKey: z.string(),
    model: z.string().default('deepseek-chat'),
    baseUrl: z.string().default('https://api.deepseek.com'),
  }),
});

export type Config = z.infer<typeof configSchema>;

export const getConfig = (): Config => {
  const result = configSchema.safeParse({
    wp: {
      url: process.env.WP_URL,
      username: process.env.WP_USERNAME,
      password: process.env.WP_PASSWORD,
    },
    deepseek: {
      apiKey: process.env.DEEPSEEK_API_KEY,
      model: process.env.DEEPSEEK_MODEL,
    },
  });

  if (result.success) {
    console.log(`[Config] Loaded WordPress configuration for ${result.data.wp.username} (Password length: ${result.data.wp.password.length})`);
  }

  if (!result.success) {
    console.error('Invalid configuration:', result.error.format());
    throw new Error('Missing or invalid environment variables.');
  }

  return result.data;
};
