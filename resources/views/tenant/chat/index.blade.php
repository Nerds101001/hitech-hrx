@extends('layouts/layoutMaster')

@section('title', __('Chat'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/app-chat.scss'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app-chat.js'])
@endsection

@section('content')
<div class="app-chat">
  <div class="row">
    <div class="col-md-4 mb-3">
      <h5 class="mb-3">@lang('Chats')</h5>
      <ul id="chat-list" class="list-group"></ul>
    </div>
    <div class="col-md-8">
      <div class="card">
        <div id="participants" class="p-2 border-bottom"></div>
        <div class="chat-history-body p-4">
          <ul id="chat-messages" class="list-unstyled mb-0">
            <li class="chat-message">
              <div class="chat-message-wrapper">
                <div class="chat-message-text mt-2">
                  <p class="mb-0 text-break">@lang('Start your conversation')</p>
                </div>
              </div>
            </li>
          </ul>
        </div>
        <div class="chat-history-footer">
          <form id="send-form" class="form-send-message d-flex p-2 border-top" enctype="multipart/form-data">
            <input type="file" name="file" class="form-control me-2" />
            <input type="text" class="form-control message-input" placeholder="@lang('Type message')" />
            <button type="submit" class="btn btn-primary ms-2">@lang('Send')</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
