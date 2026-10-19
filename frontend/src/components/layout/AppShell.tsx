import { Outlet } from 'react-router'

import { Container } from './Container'
import { Footer } from './Footer'
import { Header } from './Header'

export function AppShell() {
  return (
    <div className="flex min-h-svh flex-col">
      <Header />

      <main className="flex-1">
        <Container className="py-12 sm:py-16">
          <Outlet />
        </Container>
      </main>

      <Footer />
    </div>
  )
}
