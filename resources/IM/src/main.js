import Vue from 'vue'
import App from './App'
import router from './router'
import {
  Vuetify,
  VApp,
  VList,
  VBtn,
  VIcon,
  VGrid,
  VCard,
  VToolbar,
  VTextField,
  transitions
} from 'vuetify'
import '../node_modules/vuetify/src/stylus/app.styl'
import 'material-design-icons-iconfont/dist/material-design-icons.css'

Vue.use(Vuetify, {
  components: {
    VApp,
    VList,
    VBtn,
    VIcon,
    VGrid,
    VCard,
    VToolbar,
    VTextField,
    transitions
  },
  theme: {

  }
})

Vue.config.productionTip = false

/* eslint-disable no-new */
new Vue({
  el: '#app',
  router,
  render: h => h(App)
})
