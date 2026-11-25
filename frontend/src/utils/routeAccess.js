export function canAccessRoles(role, roles) {
  return !roles || roles.includes(role) || (role === 'curator' && roles.includes('admin'))
}

export function createAuthGuard(auth, ready) {
  return async (to) => {
    const meta = to.meta || {}
    if (meta.auth || meta.requiresAuth || meta.guest || meta.role) await ready
    if ((meta.auth || meta.requiresAuth) && !auth.isAuthenticated) {
      return { name:'login', query:{redirect:to.fullPath} }
    }
    if (!canAccessRoles(auth.user?.role, meta.role)) return { name:'home' }
    if (meta.guest && auth.isAuthenticated) return { name:'home' }
  }
}
