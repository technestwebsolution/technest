export type WordPressPost = {
  slug?: string;
  date?: string;
  link?: string;
  title?: {
    rendered?: string;
  };
  excerpt?: {
    rendered?: string;
  };
  content?: {
    rendered?: string;
  };
  _embedded?: {
    "wp:featuredmedia"?: Array<{
      source_url?: string;
    }>;
  };
};

export type BlogPost = {
  slug: string;
  date: string;
  title: string;
  excerpt: string;
  content: string;
  image: string;
  alt: string;
  link: string;
  wordpressLink: string;
};

const env = import.meta.env as Record<string, string | undefined>;
const defaultWordPressSiteUrl = "https://wordpresstechnestwebsolution.com";

const wordpressSiteUrl =
  env.WORDPRESS_SITE_URL ??
  env.PUBLIC_WORDPRESS_SITE_URL ??
  defaultWordPressSiteUrl;

const wordpressPostsApiUrl =
  env.WORDPRESS_POSTS_API_URL ?? env.PUBLIC_WORDPRESS_POSTS_API_URL ?? "";

export const WORDPRESS_BLOG_EMPTY_STATE = {
  title: "WordPress blog not configured",
  description:
    "No blog posts are available right now. Please set WORDPRESS_SITE_URL or WORDPRESS_POSTS_API_URL to load posts from WordPress.",
};

const decodeHtml = (value: string) =>
  value
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&nbsp;/g, " ");

const stripHtml = (value: string) =>
  decodeHtml(value.replace(/<[^>]*>/g, "")).replace(/\s+/g, " ").trim();

const slugify = (value: string) =>
  value
    .toLowerCase()
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

const formatDate = (value?: string) => {
  if (!value) {
    return "Recently";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(date);
};

const getBaseSiteUrl = () => wordpressSiteUrl.replace(/\/+$/, "");

const buildPostsEndpoint = (limit: number) => {
  if (wordpressPostsApiUrl) {
    return wordpressPostsApiUrl;
  }

  if (!wordpressSiteUrl) {
    return "";
  }

  return `${getBaseSiteUrl()}/wp-json/wp/v2/posts?_embed=1&per_page=${limit}&orderby=date&order=desc`;
};

const buildSlugEndpoint = (slug: string) => {
  if (!wordpressSiteUrl) {
    return "";
  }

  return `${getBaseSiteUrl()}/wp-json/wp/v2/posts?slug=${encodeURIComponent(slug)}&_embed=1`;
};

const mapWordPressPost = (post: WordPressPost, fallbackSlug = ""): BlogPost => {
  const title = decodeHtml(post?.title?.rendered ?? "Untitled Post");
  const slug = post?.slug?.trim() || fallbackSlug || slugify(title);
  const image =
    post?._embedded?.["wp:featuredmedia"]?.[0]?.source_url ||
    "/img/background_img.jpg";
  const excerpt = stripHtml(post?.excerpt?.rendered ?? "");
  const content = post?.content?.rendered?.trim()
    ? post.content.rendered
    : `<p>${excerpt}</p>`;

  return {
    slug,
    date: formatDate(post?.date),
    title,
    excerpt,
    content,
    image,
    alt: decodeHtml(post?.title?.rendered ?? "Blog post image"),
    link: `/blog/${slug}`,
    wordpressLink: post?.link ?? `/blog/${slug}`,
  };
};

export const buildBlogPath = (slug: string) => `/blog/${slug}`;

export async function fetchWordPressPosts(limit = 2): Promise<BlogPost[]> {
  const endpoint = buildPostsEndpoint(limit);

  if (!endpoint) {
    return [];
  }

  try {
    const response = await fetch(endpoint, {
      headers: {
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      return [];
    }

    const data: unknown = await response.json();

    if (!Array.isArray(data)) {
      return [];
    }

    const posts = (data as WordPressPost[])
      .map((post) => mapWordPressPost(post))
      .filter((post) => post.title && post.excerpt);

    return posts.length > 0 ? posts.slice(0, limit) : [];
  } catch {
    return [];
  }
}

export async function fetchWordPressPostBySlug(
  slug: string,
): Promise<BlogPost | null> {
  const endpoint = buildSlugEndpoint(slug);

  if (!endpoint) {
    return null;
  }

  try {
    const response = await fetch(endpoint, {
      headers: {
        Accept: "application/json",
      },
    });

    if (!response.ok) {
      return null;
    }

    const data: unknown = await response.json();

    if (!Array.isArray(data) || data.length === 0) {
      return null;
    }

    return mapWordPressPost(data[0] as WordPressPost, slug);
  } catch {
    return null;
  }
}
