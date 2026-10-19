/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

interface Window {
  /**
   * Private admin panel navigation base, injected only into the admin shell
   * document by the backend (AdminPanelController). It is never part of the
   * public frontend bundle and is navigation-only — never a security
   * boundary: all admin API endpoints enforce authentication + role checks.
   */
  __ADMIN_BASE__?: string
}
