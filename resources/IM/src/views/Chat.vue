<template>
	<v-container>
		<v-layout>
			<v-flex>
				<v-card>
					<v-toolbar card>
						<v-toolbar-side-icon></v-toolbar-side-icon>
						<v-toolbar-title>CHAT</v-toolbar-title>
						<v-spacer></v-spacer>
					</v-toolbar>

					<v-card-actions>
						<v-text-field
							v-model="msg"
							@keyup.enter="submit"
							solo
							clearable
							label="回车发送消息"
							type="text"
						>
							<v-icon slot="prepend">help</v-icon>
						</v-text-field>
					</v-card-actions>
				</v-card>
			</v-flex>
		</v-layout>
	</v-container>
</template>

<script>
  export default {
    data () {
      return {
        ws: {},
        msg: ''
      }
    },
    created () {
      this.ws = new WebSocket('ws://0.0.0.0:58582')

      this.ws.onopen = evt => {
        console.log('Connection open ...')
      }

      this.ws.onmessage = evt => {
        console.log('Received Message: ' + evt.data)
      }

      this.ws.onclose = evt => {
        console.log('Connection closed.')
      }
    },
    methods: {
      submit () {
        this.ws.send(JSON.stringify({ cmd: 'Message', data: this.msg }))
      }
    }
  }
</script>
