/**
 * App Chat
 */

'use strict';
import axios from 'axios';

document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const chatContactsBody = document.querySelector('.app-chat-contacts .sidebar-body'),
      chatContactListItems = [].slice.call(
        document.querySelectorAll('.chat-contact-list-item:not(.chat-contact-list-item-title)')
      ),
      chatHistoryBody = document.querySelector('.chat-history-body'),
      chatSidebarLeftBody = document.querySelector('.app-chat-sidebar-left .sidebar-body'),
      chatSidebarRightBody = document.querySelector('.app-chat-sidebar-right .sidebar-body'),
      chatUserStatus = [].slice.call(document.querySelectorAll(".form-check-input[name='chat-user-status']")),
      chatSidebarLeftUserAbout = $('.chat-sidebar-left-user-about'),
      formSendMessage = document.querySelector('.form-send-message'),
      messageInput = document.querySelector('.message-input'),
      searchInput = document.querySelector('.chat-search-input'),
      speechToText = $('.speech-to-text'), // ! jQuery dependency for speech to text
      chatListEl = document.getElementById('chat-list'),
      chatMessagesEl = document.getElementById('chat-messages'),
      participantsEl = document.getElementById('participants'),
      sendForm = document.getElementById('send-form'),
      fileInput = sendForm ? sendForm.querySelector('input[type="file"]') : null,
      userStatusObj = {
        active: 'avatar-online',
        offline: 'avatar-offline',
        away: 'avatar-away',
        busy: 'avatar-busy'
      };

    // Axios setup with CSRF
    if (axios) {
      axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
      axios.defaults.baseURL = baseUrl + 'api/V1/';
    }

    // Initialize PerfectScrollbar
    // ------------------------------

    // Chat contacts scrollbar
    if (chatContactsBody) {
      new PerfectScrollbar(chatContactsBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Chat history scrollbar
    if (chatHistoryBody) {
      new PerfectScrollbar(chatHistoryBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Sidebar left scrollbar
    if (chatSidebarLeftBody) {
      new PerfectScrollbar(chatSidebarLeftBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Sidebar right scrollbar
    if (chatSidebarRightBody) {
      new PerfectScrollbar(chatSidebarRightBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Scroll to bottom function
    function scrollToBottom() {
      chatHistoryBody.scrollTo(0, chatHistoryBody.scrollHeight);
    }
    scrollToBottom();

    // User About Maxlength Init
    if (chatSidebarLeftUserAbout.length) {
      chatSidebarLeftUserAbout.maxlength({
        alwaysShow: true,
        warningClass: 'label label-success bg-success text-white',
        limitReachedClass: 'label label-danger',
        separator: '/',
        validate: true,
        threshold: 120
      });
    }

    // Update user status
    chatUserStatus.forEach(el => {
      el.addEventListener('click', e => {
        let chatLeftSidebarUserAvatar = document.querySelector('.chat-sidebar-left-user .avatar'),
          value = e.currentTarget.value;
        //Update status in left sidebar user avatar
        chatLeftSidebarUserAvatar.removeAttribute('class');
        Helpers._addClass(
          'avatar avatar-xl chat-sidebar-avatar ' + userStatusObj[value] + '',
          chatLeftSidebarUserAvatar
        );
        //Update status in contacts sidebar user avatar
        let chatContactsUserAvatar = document.querySelector('.app-chat-contacts .avatar');
        chatContactsUserAvatar.removeAttribute('class');
        Helpers._addClass('flex-shrink-0 avatar ' + userStatusObj[value] + ' me-3', chatContactsUserAvatar);
      });
    });

    // Select chat or contact
    chatContactListItems.forEach(chatContactListItem => {
      // Bind click event to each chat contact list item
      chatContactListItem.addEventListener('click', e => {
        // Remove active class from chat contact list item
        chatContactListItems.forEach(chatContactListItem => {
          chatContactListItem.classList.remove('active');
        });
        // Add active class to current chat contact list item
        e.currentTarget.classList.add('active');
      });
    });

    // Filter Chats
    if (searchInput) {
      searchInput.addEventListener('keyup', e => {
        let searchValue = e.currentTarget.value.toLowerCase(),
          searchChatListItemsCount = 0,
          searchContactListItemsCount = 0,
          chatListItem0 = document.querySelector('.chat-list-item-0'),
          contactListItem0 = document.querySelector('.contact-list-item-0'),
          searchChatListItems = [].slice.call(
            document.querySelectorAll('#chat-list li:not(.chat-contact-list-item-title)')
          ),
          searchContactListItems = [].slice.call(
            document.querySelectorAll('#contact-list li:not(.chat-contact-list-item-title)')
          );

        // Search in chats
        searchChatContacts(searchChatListItems, searchChatListItemsCount, searchValue, chatListItem0);
        // Search in contacts
        searchChatContacts(searchContactListItems, searchContactListItemsCount, searchValue, contactListItem0);
      });
    }

    // Search chat and contacts function
    function searchChatContacts(searchListItems, searchListItemsCount, searchValue, listItem0) {
      searchListItems.forEach(searchListItem => {
        let searchListItemText = searchListItem.textContent.toLowerCase();
        if (searchValue) {
          if (-1 < searchListItemText.indexOf(searchValue)) {
            searchListItem.classList.add('d-flex');
            searchListItem.classList.remove('d-none');
            searchListItemsCount++;
          } else {
            searchListItem.classList.add('d-none');
          }
        } else {
          searchListItem.classList.add('d-flex');
          searchListItem.classList.remove('d-none');
          searchListItemsCount++;
        }
      });
      // Display no search fount if searchListItemsCount == 0
      if (searchListItemsCount == 0) {
        listItem0.classList.remove('d-none');
      } else {
        listItem0.classList.add('d-none');
      }
    }

    // Send Message
    formSendMessage.addEventListener('submit', e => {
      e.preventDefault();
      if (messageInput.value) {
        // Create a div and add a class
        let renderMsg = document.createElement('div');
        renderMsg.className = 'chat-message-text mt-2';
        renderMsg.innerHTML = '<p class="mb-0 text-break">' + messageInput.value + '</p>';
        document.querySelector('li:last-child .chat-message-wrapper').appendChild(renderMsg);
        messageInput.value = '';
        scrollToBottom();
      }
    });

    // on click of chatHistoryHeaderMenu, Remove data-overlay attribute from chatSidebarLeftClose to resolve overlay overlapping issue for two sidebar
    let chatHistoryHeaderMenu = document.querySelector(".chat-history-header [data-target='#app-chat-contacts']"),
      chatSidebarLeftClose = document.querySelector('.app-chat-sidebar-left .close-sidebar');
    chatHistoryHeaderMenu.addEventListener('click', e => {
      chatSidebarLeftClose.removeAttribute('data-overlay');
    });
    // }

    // Speech To Text
    if (speechToText.length) {
      var SpeechRecognition = SpeechRecognition || webkitSpeechRecognition;
      if (SpeechRecognition !== undefined && SpeechRecognition !== null) {
        var recognition = new SpeechRecognition(),
          listening = false;
        speechToText.on('click', function () {
          const $this = $(this);
          recognition.onspeechstart = function () {
            listening = true;
          };
          if (listening === false) {
            recognition.start();
          }
          recognition.onerror = function (event) {
            listening = false;
          };
          recognition.onresult = function (event) {
            $this.closest('.form-send-message').find('.message-input').val(event.results[0][0].transcript);
          };
          recognition.onspeechend = function (event) {
            listening = false;
            recognition.stop();
          };
        });
      }
    }

    // ------------------------------
    // API integration
    // ------------------------------

    let currentChatId = null;
    let lastMessageId = null;

    async function loadChats() {
      try {
        const response = await axios.get('chats');
        if (response.data && response.data.data) {
          renderChatList(response.data.data);
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function loadMessages(chatId, append = false) {
      try {
        const response = await axios.get('chats/messages', { params: { chatId } });
        if (response.data && response.data.data && response.data.data.values) {
          renderMessages(response.data.data.values, append);
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function loadParticipants(chatId) {
      try {
        const response = await axios.get(`chats/${chatId}/participants`);
        if (response.data && response.data.data) {
          renderParticipants(response.data.data);
        }
      } catch (err) {
        console.error(err);
      }
    }

    async function sendTextMessage(message) {
      if (!currentChatId) return;
      const res = await axios.post(`chats/${currentChatId}/send`, { message });
      lastMessageId = res.data.data;
      await loadMessages(currentChatId);
    }

    async function sendFileMessage(file) {
      if (!currentChatId) return;
      const formData = new FormData();
      formData.append('file', file);
      formData.append('type', 'file');
      const res = await axios.post(`chats/${currentChatId}/sendFile`, formData);
      lastMessageId = res.data.data;
      await loadMessages(currentChatId);
    }

    async function addReaction(messageId, reaction = 'like') {
      await axios.post(`chats/message/${messageId}/react`, { reaction });
    }

    async function markAsRead(messageId) {
      await axios.post(`chats/message/${messageId}/read`);
    }

    async function pollNewMessages() {
      if (!currentChatId || !lastMessageId) return;
      try {
        const response = await axios.get('chats/getNewChatMessages', {
          params: { chatId: currentChatId, lastMessageId }
        });
        if (response.data && response.data.data && response.data.data.length) {
          renderMessages(response.data.data, true);
        }
      } catch (err) {
        console.error(err);
      }
    }

    // Render functions
    function renderChatList(chats) {
      if (!chatListEl) return;
      chatListEl.innerHTML = '';
      chats.forEach(chat => {
        const li = document.createElement('li');
        li.className = 'list-group-item chat-item';
        li.dataset.chatId = chat.id;
        li.textContent = chat.name;
        chatListEl.appendChild(li);
      });
    }

    function renderMessages(messages, append = false) {
      if (!chatMessagesEl) return;
      if (!append) chatMessagesEl.innerHTML = '';
      messages.forEach(msg => {
        const li = document.createElement('li');
        li.className = 'chat-message';
        li.dataset.id = msg.id;
        li.innerHTML = `<div class="chat-message-wrapper"><div class="chat-message-text mt-2"><p class="mb-0 text-break">${msg.content ?? ''}</p></div></div><div class="small text-muted">${msg.userName} - ${msg.createdAtHuman}</div><button class="btn btn-sm btn-outline-secondary react-btn" data-message="${msg.id}">👍</button>`;
        chatMessagesEl.appendChild(li);
        lastMessageId = msg.id;
        markAsRead(msg.id);
      });
      scrollToBottom();
    }

    function renderParticipants(users) {
      if (!participantsEl) return;
      participantsEl.innerHTML = users.map(u => `<span class="badge bg-info me-1">${u.firstName}</span>`).join('');
    }

    // Event listeners
    if (chatListEl) {
      chatListEl.addEventListener('click', async e => {
        if (e.target.matches('.chat-item')) {
          currentChatId = e.target.dataset.chatId;
          await loadMessages(currentChatId);
          await loadParticipants(currentChatId);
        }
      });
    }

    if (sendForm) {
      sendForm.addEventListener('submit', async e => {
        e.preventDefault();
        if (fileInput && fileInput.files.length) {
          await sendFileMessage(fileInput.files[0]);
          fileInput.value = '';
        } else if (messageInput && messageInput.value) {
          await sendTextMessage(messageInput.value);
          messageInput.value = '';
        }
      });
    }

    if (chatMessagesEl) {
      chatMessagesEl.addEventListener('click', async e => {
        if (e.target.classList.contains('react-btn')) {
          const messageId = e.target.dataset.message;
          await addReaction(messageId);
        }
      });
    }

    // initial load
    loadChats();
    setInterval(pollNewMessages, 5000);
  })();
});
