import {beforeEach,afterEach,describe,it,expect,vi} from 'vitest'
import {mount,flushPromises} from '@vue/test-utils'
import {createPinia} from 'pinia'
import ModeStatsPanel from '@/components/minecraft/ModeStatsPanel.vue'
const mocked=vi.hoisted(()=>({get:vi.fn()}))
vi.mock('@/services/core/api.js',()=>({api:mocked}))
const data=(page=1)=>({configured:true,summary:{},players:[],arenas:[],history:[],personal:null,tables:[{key:'current',label:'Новая карта'},{key:'classic',label:'Архив'}],meta:{current_page:page,last_page:3,total:45}})
const make=()=>mount(ModeStatsPanel,{props:{mode:'fastbridge'},global:{plugins:[createPinia()],stubs:{RouterLink:true}}})
afterEach(()=>{vi.useRealTimers();vi.restoreAllMocks()})
beforeEach(()=>{mocked.get.mockReset();mocked.get.mockImplementation((url,opt)=>Promise.resolve(data(opt.params.page)))})
describe('Mode statistics requests',()=>{
  it('resets pagination and requests a changed filter only once',async()=>{
    const w=make();await flushPromises()
    await w.findAll('.mode-pager button')[1].trigger('click');await flushPromises()
    expect(mocked.get.mock.lastCall[1].params.page).toBe(2)
    mocked.get.mockClear()
    await w.find('select').setValue('64');await flushPromises()
    expect(mocked.get).toHaveBeenCalledTimes(1)
    expect(mocked.get.mock.lastCall[1].params).toMatchObject({page:1,distance:'64'})
    w.unmount()
  })
  it('does not replace a pending initial request with polling requests',async()=>{
    vi.useFakeTimers();let resolve
    mocked.get.mockReturnValue(new Promise(r=>resolve=r))
    const w=make();await vi.advanceTimersByTimeAsync(60000)
    expect(mocked.get).toHaveBeenCalledTimes(1)
    resolve(data());await flushPromises()
    expect(w.text()).not.toContain('Загружаю данные сервера')
    w.unmount();expect(vi.getTimerCount()).toBe(0)
  })
  it('pauses polling in a hidden tab and refreshes when visible',async()=>{
    vi.useFakeTimers();const hidden=vi.spyOn(document,'hidden','get').mockReturnValue(true)
    const w=make();await flushPromises();mocked.get.mockClear()
    await vi.advanceTimersByTimeAsync(60000);expect(mocked.get).not.toHaveBeenCalled()
    hidden.mockReturnValue(false);document.dispatchEvent(new Event('visibilitychange'));await flushPromises()
    expect(mocked.get).toHaveBeenCalledTimes(1);w.unmount()
  })
  it('cancels obsolete requests when filters change and on unmount',async()=>{
    const w=make();await flushPromises();const first=mocked.get.mock.lastCall[1].signal
    await w.find('select').setValue('16');await flushPromises()
    expect(first.aborted).toBe(true)
    const latest=mocked.get.mock.lastCall[1].signal;w.unmount();expect(latest.aborted).toBe(true)
  })
})
