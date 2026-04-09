@extends('layouts/layoutMaster')

@section('title', 'AI Chat Assistant')

@if(env('APP_DEMO'))

  @section('content')
    <div class="alert alert-warning">
      This feature is disabled in the demo environment.
    </div>
  @endsection

@elseif(!$settings->enable_ai_chat_global || !$settings->enable_ai_for_admin || !$settings->enable_ai_for_business_intelligence)

  @section('content')
    <div class="alert alert-warning">
      AI Chat Assistant is not enabled for this account.
    </div>
  @endsection
@else

  @section('page-script')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const queryForm = document.getElementById('query-form');
        const queryInput = document.getElementById('query');
        const submitButton = document.querySelector('button[type="submit"]');
        const chatContainer = document.getElementById('chat-container');
        const suggestionChips = document.querySelectorAll('.suggestion-chip');

        // Auto Scroll to Bottom
        function scrollToBottom() {
          chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Display Message in Chat
        function displayMessage(sender, message, type = 'response') {
          const messageBubble = document.createElement('div');
          messageBubble.classList.add('message', `${type}-message`);
          messageBubble.innerHTML = `<strong>${sender}:</strong> ${message}`;
          chatContainer.appendChild(messageBubble);
          scrollToBottom();
        }

        // Handle Query Submission
        queryForm.addEventListener('submit', function (event) {
          event.preventDefault();

          const query = queryInput.value.trim();
          if (!query) return;

          displayMessage('You', query, 'user');
          queryInput.value = '';

          // Show loader
          submitButton.disabled = true;
          submitButton.innerHTML = 'Submitting... <span class="spinner-border spinner-border-sm"></span>';
          displayMessage('AI Bot', '<em>Analyzing...</em>', 'loader');

          fetch('/aiChat/query', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({query})
          })
            .then(response => response.json())
            .then(data => {
              document.querySelector('.loader-message').remove();
              displayMessage('AI Bot', data.response);
            })
            .catch(error => {
              document.querySelector('.loader-message').remove();
              displayMessage('Error', error.message, 'error');
            })
            .finally(() => {
              submitButton.disabled = false;
              submitButton.innerHTML = 'Send';
            });
        });

        // Handle Suggestion Chip Click
        suggestionChips.forEach(chip => {
          chip.addEventListener('click', function () {
            const suggestion = this.textContent;
            queryInput.value = suggestion;
            queryForm.dispatchEvent(new Event('submit'));
          });
        });
      });
    </script>
  @endsection

  @section('content')
    <div class="container-fluid p-0">
      <div class="chat-wrapper d-flex flex-column" style="height: 60vh;">
        <!-- Chat Header -->
        <div class="chat-header d-flex align-items-center justify-content-between p-3 border-bottom">
          <h5 class="mb-0">AI Chat Assistant <span class="badge bg-danger">Beta</span></h5>
          <button class="btn btn-primary btn-sm" onclick="location.reload()">Refresh</button>
        </div>

        <!-- Chat Container -->
        <div id="chat-container" class="chat-container flex-grow-1 p-3">
          <!-- Chat messages will dynamically appear here -->
        </div>

        <!-- Suggestion Chips -->
        <div class="chat-suggestions p-2 border-top">
          <div class="chip-container d-flex flex-nowrap gap-2">
            <button class="btn btn-outline-secondary suggestion-chip">Show employee attendance</button>
            <button class="btn btn-outline-secondary suggestion-chip">List all pending leave requests</button>
            <button class="btn btn-outline-secondary suggestion-chip">Monthly salary summary</button>
            <button class="btn btn-outline-secondary suggestion-chip">Employee work hours for today</button>
            <button class="btn btn-outline-secondary suggestion-chip">Upcoming holidays</button>
          </div>
        </div>

        <!-- Chat Input -->
        <form id="query-form" class="chat-input border-top p-2 d-flex gap-2">
          <textarea id="query" class="form-control" placeholder="Type your message..." rows="1"></textarea>
          <button type="submit" class="btn btn-primary">Send</button>
        </form>
      </div>
    </div>

    <style>
      /* Suggestion Chips Container */
      .chat-suggestions {
        border-top: 1px solid #dee2e6;
        overflow-x: auto; /* Enable horizontal scrolling */
        padding: 10px;
      }

      .chip-container {
        display: flex;
        gap: 10px; /* Add space between chips */
        flex-wrap: nowrap; /* Prevent wrapping on small screens */
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on touch devices */
      }

      .suggestion-chip {
        white-space: nowrap; /* Prevent text wrapping in buttons */
        font-size: 0.9rem;
      }

      /* Chat Wrapper */
      .chat-wrapper {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 60vh; /* Adjusted height */
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background-color: #f8f9fa;
        overflow: hidden;
        margin: 0; /* Ensure no margin is set */
      }

      /* Chat Header */
      .chat-header {
        border-bottom: 1px solid #dee2e6;
        color: white;
        font-weight: bold;
        padding: 10px 15px;
        flex-shrink: 0; /* Prevent shrinking */
      }

      /* Chat Container */
      .chat-container {
        flex-grow: 1; /* Allow chat messages to take up remaining space */
        overflow-y: auto; /* Enable vertical scrolling */
        display: flex;
        flex-direction: column;
        justify-content: flex-start; /* Align messages at the top */
        padding: 10px;
        margin: 0; /* Ensure no margin is set */
      }

      /* Chat Messages */
      .message {
        padding: 10px 12px;
        border-radius: 10px;
        margin-bottom: 10px;
        max-width: 70%;
        word-wrap: break-word;
      }

      .user-message {
        background-color: #007bff;
        color: white;
        align-self: flex-end;
        text-align: right;
      }

      .response-message {
        background-color: var(--bs-primary, #007bff);
        color: white;
        align-self: flex-start;
      }

      .error-message {
        background-color: #f8d7da;
        color: #721c24;
      }

      /* Chat Input */
      .chat-input {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        border-top: 1px solid #dee2e6;
        background: white;
        flex-shrink: 0; /* Prevent shrinking */
      }

      .chat-input textarea {
        resize: none;
        overflow: hidden;
        max-height: 60px;
        flex: 1;
        margin-right: 10px;
      }

      .chat-input button {
        white-space: nowrap;
      }
    </style>
  @endsection

@endif
