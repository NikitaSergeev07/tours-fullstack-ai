// Express SSR entry. Vike provides the renderer used in `renderPage`.
// In dev we plug in the Vite middleware; in prod we serve the built assets.

import express from 'express'
import compression from 'compression'
import { renderPage } from 'vike/server'

const isProd = process.env.NODE_ENV === 'production'
const port = parseInt(process.env.PORT || '3000', 10)
const root = process.cwd()

async function startServer() {
  const app = express()

  if (isProd) {
    app.use(compression())
    const sirv = (await import('sirv')).default
    app.use(sirv(`${root}/dist/client`, { extensions: [] }))
  } else {
    const { createServer: createViteServer } = await import('vite')
    const vite = await createViteServer({
      root,
      server: { middlewareMode: true },
      appType: 'custom',
    })
    app.use(vite.middlewares)
  }

  app.get('*', async (req, res, next) => {
    const pageContextInit = {
      urlOriginal: req.originalUrl,
      headersOriginal: req.headers,
    }
    const pageContext = await renderPage(pageContextInit)
    const { httpResponse } = pageContext
    if (!httpResponse) return next()
    const { body, statusCode, headers } = httpResponse
    headers.forEach(([name, value]) => res.setHeader(name, value))
    res.status(statusCode).send(body)
  })

  app.listen(port, () => {
    console.log(`Tours frontend ready on http://localhost:${port}`)
  })
}

startServer()
