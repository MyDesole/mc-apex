import {describe,it,expect} from 'vitest'
import {createAuthGuard,canAccessRoles} from '@/utils/routeAccess.js'
const deferred=()=>{let resolve;const promise=new Promise(r=>resolve=r);return {promise,resolve}}
describe('Navigation initialization',()=>{
  it('opens public pages without waiting for the account request',async()=>{
    const guard=createAuthGuard({isAuthenticated:false,user:null},new Promise(()=>{}))
    expect(await guard({meta:{},fullPath:'/'})).toBeUndefined()
  })
  it.each(['auth','requiresAuth'])('waits for account resolution and protects %s routes',async(key)=>{
    const ready=deferred(),auth={isAuthenticated:false,user:null}
    let settled=false
    const pending=createAuthGuard(auth,ready.promise)({meta:{[key]:true},fullPath:'/messages/42?x=1'}).then(v=>{settled=true;return v})
    await Promise.resolve();expect(settled).toBe(false)
    ready.resolve()
    expect(await pending).toEqual({name:'login',query:{redirect:'/messages/42?x=1'}})
  })
  it('keeps a valid deep link after the account arrives',async()=>{
    const ready=deferred(),auth={isAuthenticated:false,user:null}
    const pending=createAuthGuard(auth,ready.promise)({meta:{auth:true},fullPath:'/profile'})
    auth.isAuthenticated=true;auth.user={role:'user'};ready.resolve()
    expect(await pending).toBeUndefined()
  })
  it('applies guest and staff restrictions after initialization',async()=>{
    expect(await createAuthGuard({isAuthenticated:true,user:{role:'user'}},Promise.resolve())({meta:{guest:true}})).toEqual({name:'home'})
    expect(await createAuthGuard({isAuthenticated:true,user:{role:'user'}},Promise.resolve())({meta:{auth:true,role:['admin']}})).toEqual({name:'home'})
  })
  it('shows curators the same allowed admin menu links as the route guard',()=>{
    expect(canAccessRoles('curator',['tester','admin'])).toBe(true)
    expect(canAccessRoles('user',['tester','admin'])).toBe(false)
    expect(canAccessRoles('bridge_tester',['bridge_tester','admin'])).toBe(true)
  })
})
