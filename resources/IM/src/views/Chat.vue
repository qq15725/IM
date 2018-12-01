<template>

</template>

<script>
  export default {
    created () {
      const ws = new WebSocket('ws://0.0.0.0:58582')

      ws.onopen = evt => {
        console.log('Connection open ...')
        ws.send('Hello WebSockets!')
      }

      ws.onmessage = evt => {
        console.log('Received Message: ' + evt.data)
        ws.close()
      }

      ws.onclose = evt => {
        console.log('Connection closed.')
      }
    }
  }
</script>
