import { z } from 'zod';

export const CommandSchema = z.discriminatedUnion('type', [
  z.object({
    type: z.literal('create_page'),
    title: z.string(),
    blocks: z.array(z.any()),
    status: z.enum(['draft', 'publish']).default('draft'),
  }),
  z.object({
    type: z.literal('insert_pattern'),
    pattern_slug: z.string(),
    target_post_id: z.coerce.number(),
    context: z.record(z.any()).optional(),
  }),
  z.object({
    type: z.literal('update_global_styles'),
    styles: z.record(z.any()),
    settings: z.record(z.any()).optional(),
  }),
  z.object({
    type: z.literal('update_post'),
    post_id: z.coerce.number(),
    title: z.string().optional(),
    content: z.string().optional(),
    status: z.enum(['draft', 'publish']).optional(),
  }),
  z.object({
    type: z.literal('update_settings'),
    title: z.string().optional(),
    description: z.string().optional(),
    timezone: z.string().optional(),
  }),
  z.object({
    type: z.literal('upload_media'),
    url: z.string().url(),
    alt_text: z.string().optional(),
    caption: z.string().optional(),
  }),
  z.object({
    type: z.literal('patch_post_content'),
    post_id: z.coerce.number(),
    search: z.string(),
    replace: z.string(),
  }),
]);

export type Command = z.infer<typeof CommandSchema>;

export const OrchestrationPlanSchema = z.object({
  explanation: z.string(),
  commands: z.array(CommandSchema),
});

export type OrchestrationPlan = z.infer<typeof OrchestrationPlanSchema>;
