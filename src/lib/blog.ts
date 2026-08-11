export type BlogPost = {
  slug: string;
  date: string;
  title: string;
  excerpt: string;
  content: string;
  image: string;
  alt: string;
  link: string;
};

export const blogPosts: BlogPost[] = [
  {
    slug: "laravel-best-php-framework-saas-products",
    date: "April 30, 2026",
    title: "Why Laravel is the Best PHP Framework for Building SaaS Products",
    excerpt:
      "Discover why Laravel is becoming the go-to framework for building blazing fast websites in 2026 and how it helps modern teams ship reliable web products.",
    image: "/img/blog1.jpeg",
    alt: "Laravel blog feature image",
    link: "/blog/laravel-best-php-framework-saas-products",
    content: `
      <p>Laravel continues to be one of the strongest choices for SaaS products because it balances developer experience, security, and long-term maintainability. Teams can move quickly without sacrificing structure.</p>
      <h2>Built for real products</h2>
      <p>From authentication to queues, scheduling, caching, and database migrations, Laravel includes the building blocks needed for production software. That reduces dependency sprawl and keeps projects easier to maintain.</p>
      <h2>Fast development without shortcuts</h2>
      <p>The framework encourages clean architecture while still giving developers the tools to ship features quickly. That matters when product requirements change and timelines are tight.</p>
      <h2>Why it works well for SaaS</h2>
      <ul>
        <li>Strong ecosystem for billing, auth, and APIs</li>
        <li>Readable codebase that scales with the team</li>
        <li>Excellent support for testing and automation</li>
      </ul>
      <p>If your SaaS needs a stable foundation that can grow with your business, Laravel is still a very practical choice.</p>
    `,
  },
  {
    slug: "astro-js-fastest-way-build-websites-2025",
    date: "December 31, 2025",
    title: "What is Astro JS and Why is it the Fastest Way to Build Websites in 2025?",
    excerpt:
      "Discover why Astro JS is becoming the go-to framework for building blazing fast websites in 2025 and how it outperforms older approaches for modern development.",
    image: "/img/blog2.jpg",
    alt: "Astro blog feature image",
    link: "/blog/astro-js-fastest-way-build-websites-2025",
    content: `
      <p>Astro is designed around one idea: ship less JavaScript by default. That makes it a strong fit for marketing sites, content-heavy pages, and performance-focused web projects.</p>
      <h2>Performance first</h2>
      <p>By rendering static HTML and only hydrating interactive parts when needed, Astro keeps pages fast and lightweight. That improves both user experience and search performance.</p>
      <h2>Flexible by design</h2>
      <p>Astro works well with React, Vue, Svelte, and other UI frameworks, so teams can reuse components without locking themselves into a single approach.</p>
      <h2>Where it fits best</h2>
      <ul>
        <li>Agency and portfolio websites</li>
        <li>Landing pages and content sites</li>
        <li>Projects where speed and simplicity matter</li>
      </ul>
      <p>For teams that want modern development without unnecessary runtime cost, Astro is a strong option.</p>
    `,
  },
];

export const buildBlogPath = (slug: string) => `/blog/${slug}`;

export const getBlogPostBySlug = (slug: string) =>
  blogPosts.find((post) => post.slug === slug) ?? null;
