import { defineCollection, z } from 'astro:content';

const site = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string().optional(),
    name: z.string().optional(),
    credentials: z.string().optional(),
    photo: z.string().optional(),
  }),
});

export const collections = { site };
