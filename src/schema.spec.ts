import { CommandSchema, OrchestrationPlanSchema } from './schema';

describe('CommandSchema', () => {
  it('should validate a valid create_page command', () => {
    const cmd = {
      type: 'create_page',
      title: 'About Us',
      blocks: [],
      status: 'draft'
    };
    expect(CommandSchema.parse(cmd)).toEqual(cmd);
  });

  it('should validate a valid insert_pattern command', () => {
    const cmd = {
      type: 'insert_pattern',
      pattern_slug: 'hero-section',
      target_post_id: 123
    };
    expect(CommandSchema.parse(cmd)).toEqual(cmd);
  });

  it('should fail on invalid command type', () => {
    const cmd = {
      type: 'invalid_type',
      data: {}
    };
    expect(() => CommandSchema.parse(cmd)).toThrow();
  });
});

describe('OrchestrationPlanSchema', () => {
  it('should validate a valid plan', () => {
    const plan = {
      explanation: 'Test plan',
      commands: [
        {
          type: 'create_page',
          title: 'Home',
          blocks: []
        }
      ]
    };
    expect(OrchestrationPlanSchema.parse(plan)).toHaveProperty('explanation', 'Test plan');
  });
});
