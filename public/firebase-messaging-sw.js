importScripts('https://www.gstatic.com/firebasejs/9.17.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.17.1/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyCVG-QMbNtp5P0pNPY_4Xs7UIkzsHx9z-g",
  authDomain: "blask-56be1.firebaseapp.com",
  projectId: "blask-56be1",
  storageBucket: "blask-56be1.appspot.com",
  messagingSenderId: "727629014642",
  appId: "1:727629014642:web:4d856998f877a2fd0c44eb",
  measurementId: "G-H92STGD22P"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('Received background message ', payload);

  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/firebase-logo.png'
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
