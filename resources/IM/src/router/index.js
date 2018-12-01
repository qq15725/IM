import Vue from 'vue'
import Router from 'vue-router'

Vue.use(Router)

export default new Router({
  routes: [
    {
      path: '/',
      name: 'Chat',
      component: resolve => import('@/views/Chat').then(resolve)
    }
  ]
})
