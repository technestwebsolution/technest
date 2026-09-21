# TechNest Web Solution

## Contact form mail setup

The contact form submits to `public/api/contact.php` and sends mail through PHPMailer. Before deploying the PHP endpoint:

1. Run `composer install` in the project root.
2. Copy `.env.example` to `.env` and fill in the SMTP values.
3. Build the Astro site with `npm run build`.
4. Deploy the generated `dist` files together with the Composer `vendor` directory and the root `.env` file. The server must support PHP for `/api/contact.php`.

The endpoint accepts only `POST` requests, validates the submitted fields, checks the same origin, blocks honeypot submissions, and rate-limits each IP to one submission per minute.

```sh
npm create astro@latest -- --template basics
```

> 🧑‍🚀 **Seasoned astronaut?** Delete this file. Have fun!

## 🚀 Project Structure

Inside of your Astro project, you'll see the following folders and files:

```text
/
├── public/
│   └── favicon.svg
├── src
│   ├── assets
│   │   └── astro.svg
│   ├── components
│   │   └── Welcome.astro
│   ├── layouts
│   │   └── Layout.astro
│   └── pages
│       └── index.astro
└── package.json
```

To learn more about the folder structure of an Astro project, refer to [our guide on project structure](https://docs.astro.build/en/basics/project-structure/).

## 🧞 Commands

All commands are run from the root of the project, from a terminal:

| Command                   | Action                                           |
| :------------------------ | :----------------------------------------------- |
| `npm install`             | Installs dependencies                            |
| `npm run dev`             | Starts local dev server at `localhost:4321`      |
| `npm run build`           | Build your production site to `./dist/`          |
| `npm run preview`         | Preview your build locally, before deploying     |
| `npm run astro ...`       | Run CLI commands like `astro add`, `astro check` |
| `npm run astro -- --help` | Get help using the Astro CLI                     |

## 👀 Want to learn more?

Feel free to check [our documentation](https://docs.astro.build) or jump into our [Discord server](https://astro.build/chat).

## Blog Content

The blog section uses local static posts defined in `src/lib/blog.ts`, so it does not require any external CMS configuration.
