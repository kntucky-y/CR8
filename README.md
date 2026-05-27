# CR8

CR8 is a Cebu-focused e-commerce prototype with two web apps and a shared PHP API.

This project is already deployed and running on the school server for both the storefront and admin apps.

- cr8: customer-facing storefront (Vite + React + Tailwind)
- cr8admin: admin dashboard (Vite + React + Tailwind + Chart.js)
- api: PHP endpoints used by both apps

## Repository layout

- cr8/: Storefront app and PHP API for customer flows
- cr8admin/: Admin app and admin PHP API
- cr8images/: Product and brand images
- uploads/: Uploaded assets (server runtime)
- vendor/: PHP vendor dependencies (committed in this repo)

## Tech stack

- Frontend: React 19, Vite, TypeScript, Tailwind CSS
- Backend: PHP (JSON API endpoints under api/)
- Charts (admin): Chart.js via react-chartjs-2

## Quick start (storefront)

1) Install dependencies

   - From cr8/, run: `npm install`

2) Start dev server

   - From cr8/, run: `npm run dev`

## Quick start (admin)

1) Install dependencies

   - From cr8admin/, run: `npm install`

2) Start dev server

   - From cr8admin/, run: `npm run dev`

## API notes

- PHP endpoints live under cr8/api/ and cr8admin/api/.
- Endpoints return JSON and support standard REST methods (GET, POST, PUT, DELETE, OPTIONS).
- Configuration details are intentionally not documented here to avoid exposing sensitive information.

## Environment and deployment

- Production deployments are managed on the school server.
- Local development uses the Vite dev server for each app.

## Images and assets

- cr8images/ contains product images grouped by brand.
- public/ and src/assets/ contain static app assets for each frontend.

## License

All rights reserved. This project is not licensed for copying, redistribution, or derivative use.
