import Echo from 'laravel-echo';

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss']
});

console.log('Echo.js loaded');
/*
window.Echo.channel('device-updates').on('DeviceStatusUpdated', event => {
  console.log('DeviceStatusUpdated');
  console.log(event);
});*/

/*window.Echo.channel('device').listen('DeviceStatusUpdated', event => {
  console.log(event);
});*/

/*
window.Echo.channel('chat').listen('Example', event => {
  console.log(event);
});
*/
