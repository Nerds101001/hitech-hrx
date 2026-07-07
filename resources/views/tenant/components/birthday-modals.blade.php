<style>
/* Custom Birthday Styles */
.birthday-confetti-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 9999;
}
.birthday-modal-body {
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border-radius: 12px;
}
.birthday-header {
    text-align: center;
    padding: 2rem 1rem 1rem;
}
.birthday-header img.bday-icon {
    width: 80px;
    height: 80px;
    margin-bottom: 1rem;
    animation: bounce 2s infinite;
}
.birthday-wishes-list {
    max-height: 300px;
    overflow-y: auto;
}
.wish-card {
    background: #fff;
    border: 1px solid #ffe4e6;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 6px -1px rgba(254, 205, 211, 0.1);
}
.wish-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0.5rem;
}
.wish-card-header img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}
.wish-card-name {
    font-weight: bold;
    color: #e11d48;
}
.wish-message {
    color: #4b5563;
    font-style: italic;
}
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.colleague-bday-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 10px;
    margin-bottom: 1rem;
}
.colleague-bday-card img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}
</style>

<!-- Colleague Birthday & Anniversary Modal -->
<div class="modal fade" id="colleagueBirthdayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content birthday-modal-body">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-0">
        <div class="birthday-header">
            <h3 class="fw-bold" style="color: #e11d48;" id="colleagueCelebrationsTitle">🎉 Today's Celebrations!</h3>
            <p class="text-muted" id="colleagueCelebrationsSubtitle">Send a warm wish to your colleagues.</p>
        </div>
        <div id="colleaguesListContainer"></div>
      </div>
    </div>
  </div>
</div>

<!-- My Birthday & Anniversary Modal -->
<div class="modal fade" id="myBirthdayModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content birthday-modal-body">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="markWishesRead()"></button>
      </div>
      <div class="modal-body pt-0">
        <div class="birthday-header">
            <div style="font-size: 5rem;" id="myCelebrationEmoji">🥳</div>
            <h2 class="fw-extrabold" style="color: #e11d48; font-size: 2.5rem;" id="myCelebrationTitle">Happy Birthday!</h2>
            <p class="fs-5 text-muted" id="myCelebrationSubtitle">Wishing you a fantastic year ahead. Here are some wishes from your team:</p>
        </div>
        <div class="birthday-wishes-list px-3 pb-3" id="myWishesListContainer">
            <!-- Wishes will be appended here -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Send Wish Modal -->
<div class="modal fade" id="sendWishModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sendWishModalTitle">Send a Wish</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="sendWishForm" onsubmit="submitWish(event)">
            @csrf
            <input type="hidden" id="wishReceiverId" name="receiver_id">
            <input type="hidden" id="wishType" name="wish_type" value="birthday">
            <div class="mb-3">
                <textarea class="form-control" id="wishMessage" name="message" rows="4" placeholder="Write a personalized message..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="sendWishBtn">Send Wish 🎈</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
function _he(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    // Listen for modal close to prevent re-showing today
    var bdayModalEl = document.getElementById('colleagueBirthdayModal');
    if (bdayModalEl) {
        bdayModalEl.addEventListener('hidden.bs.modal', function (event) {
            localStorage.setItem('bday_popup_closed_date', new Date().toDateString());
        });
    }

    // Automatically check for birthdays today
    checkBirthdays();
    
    // If using Echo/Reverb, listen for BirthdayWishNotification
    if (typeof Echo !== 'undefined' && window.Laravel && window.Laravel.userId) {
        Echo.private('App.Models.User.' + window.Laravel.userId)
            .notification((notification) => {
                if (notification.type === 'birthday_wish') {
                    showRealtimeWish(notification);
                }
            });
    }
});

function checkBirthdays() {
    $.ajax({
        url: "{{ route('birthdays.check') }}",
        type: 'GET',
        success: function(response) {
            // 1. My Birthday wishes popup
            if (response.isMyBirthday && response.unreadWishes && response.unreadWishes.length > 0) {
                $('#myCelebrationEmoji').text('🎂');
                $('#myCelebrationTitle').text('Happy Birthday!');
                $('#myCelebrationSubtitle').text('Wishing you a fantastic year ahead. Here are some wishes from your team:');
                showMyCelebration(response.unreadWishes);
            }
            
            // 2. My Work Anniversary wishes popup
            else if (response.isMyAnniversary && response.unreadAnniversaryWishes && response.unreadAnniversaryWishes.length > 0) {
                $('#myCelebrationEmoji').text('🏅');
                $('#myCelebrationTitle').text('Happy Work Anniversary!');
                $('#myCelebrationSubtitle').text('Congratulations on another great year with us! Here are some wishes from your team:');
                showMyCelebration(response.unreadAnniversaryWishes);
            }
            
            // 3. Colleague celebrations (Birthdays & Anniversaries) popup
            const birthdaysCount = response.colleagues ? response.colleagues.length : 0;
            const anniversariesCount = response.colleagueAnniversaries ? response.colleagueAnniversaries.length : 0;
            
            if (birthdaysCount > 0 || anniversariesCount > 0) {
                let hasUnwishedBday = response.colleagues && response.colleagues.some(c => !c.is_wished);
                let hasUnwishedAnniv = response.colleagueAnniversaries && response.colleagueAnniversaries.some(c => !c.is_wished);
                let alreadyClosedToday = localStorage.getItem('bday_popup_closed_date') === new Date().toDateString();

                if ((hasUnwishedBday || hasUnwishedAnniv) && !alreadyClosedToday) {
                    setTimeout(() => {
                        if (!$('#myBirthdayModal').is(':visible')) {
                            showColleaguesCelebrations(response.colleagues, response.colleagueAnniversaries, true);
                        }
                    }, 2000);
                } else {
                    // Populate silently for manual open
                    showColleaguesCelebrations(response.colleagues, response.colleagueAnniversaries, false);
                }
            }
        }
    });
}

function showMyCelebration(wishes) {
    let container = document.getElementById('myWishesListContainer');
    container.innerHTML = '';
    
    wishes.forEach(wish => {
        let defaultAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(wish.sender_name) + '&background=e11d48&color=fff';
        let avatar = wish.sender_avatar ? wish.sender_avatar : defaultAvatar;
        let card = `
            <div class="wish-card">
                <div class="wish-card-header">
                    <img src="${_he(avatar)}" alt="Avatar" onerror="this.onerror=null; this.src='${defaultAvatar}';">
                    <div class="wish-card-name">${_he(wish.sender_name)}</div>
                </div>
                <div class="wish-message">"${_he(wish.message)}"</div>
            </div>
        `;
        container.innerHTML += card;
    });

    var myModal = new bootstrap.Modal(document.getElementById('myBirthdayModal'));
    myModal.show();
    
    triggerConfetti();
}

function showRealtimeWish(wish) {
    let container = document.getElementById('myWishesListContainer');
    let defaultAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(wish.sender_name) + '&background=e11d48&color=fff';
    let avatar = wish.sender_avatar ? wish.sender_avatar : defaultAvatar;
    let card = `
        <div class="wish-card">
            <div class="wish-card-header">
                <img src="${_he(avatar)}" alt="Avatar" onerror="this.onerror=null; this.src='${defaultAvatar}';">
                <div class="wish-card-name">${_he(wish.sender_name)}</div>
            </div>
            <div class="wish-message">"${_he(wish.message)}"</div>
        </div>
    `;
    
    // If modal is open, append. If not, open it.
    if ($('#myBirthdayModal').is(':visible')) {
        container.innerHTML += card;
        triggerConfetti();
    } else {
        container.innerHTML = card;
        var myModal = new bootstrap.Modal(document.getElementById('myBirthdayModal'));
        myModal.show();
        triggerConfetti();
    }
}

function showColleaguesCelebrations(birthdays, anniversaries, showModal = true) {
    let container = document.getElementById('colleaguesListContainer');
    container.innerHTML = '';
    
    // Render birthdays
    if (birthdays && birthdays.length > 0) {
        birthdays.forEach(user => {
            let fullName = user.first_name + ' ' + user.last_name;
            let defaultAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=e11d48&color=fff';
            let avatar = user.avatar_url ? user.avatar_url : defaultAvatar;
            let buttonHtml = user.is_wished
                ? `<button class="btn btn-sm btn-outline-secondary rounded-pill" disabled>Wished <i class="bx bx-check ms-1"></i></button>`
                : `<button class="btn btn-sm btn-outline-danger rounded-pill" data-uid="${_he(user.id)}" data-uname="${_he(user.first_name)}" data-wtype="birthday" onclick="openSendWishModal(this.dataset.uid, this.dataset.uname, this.dataset.wtype)">Wish <i class="bx bx-send ms-1"></i></button>`;

            let card = `
                <div class="colleague-bday-card d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <img src="${_he(avatar)}" alt="Avatar" onerror="this.onerror=null; this.src='${defaultAvatar}';">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">${_he(fullName)}</h6>
                            <small class="text-muted"><i class="bx bx-cake text-primary"></i> Birthday Today!</small>
                        </div>
                    </div>
                    ${buttonHtml}
                </div>
            `;
            container.innerHTML += card;
        });
    }

    // Render anniversaries
    if (anniversaries && anniversaries.length > 0) {
        anniversaries.forEach(user => {
            let fullName = user.first_name + ' ' + user.last_name;
            let defaultAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=004D4D&color=fff';
            let avatar = user.avatar_url ? user.avatar_url : defaultAvatar;
            let buttonHtml = user.is_wished
                ? `<button class="btn btn-sm btn-outline-secondary rounded-pill" disabled>Congratulated <i class="bx bx-check ms-1"></i></button>`
                : `<button class="btn btn-sm btn-outline-success rounded-pill" data-uid="${_he(user.id)}" data-uname="${_he(user.first_name)}" data-wtype="anniversary" onclick="openSendWishModal(this.dataset.uid, this.dataset.uname, this.dataset.wtype)">Wish <i class="bx bx-send ms-1"></i></button>`;

            let card = `
                <div class="colleague-bday-card d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <img src="${_he(avatar)}" alt="Avatar" onerror="this.onerror=null; this.src='${defaultAvatar}';">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">${_he(fullName)}</h6>
                            <small class="text-muted"><i class="bx bx-medal text-warning"></i> Work Anniversary! (${user.milestone} Yr Milestone)</small>
                        </div>
                    </div>
                    ${buttonHtml}
                </div>
            `;
            container.innerHTML += card;
        });
    }
    
    if (showModal) {
        var myModal = new bootstrap.Modal(document.getElementById('colleagueBirthdayModal'));
        myModal.show();
    }
}

function openSendWishModal(userId, userName, wishType) {
    $('#colleagueBirthdayModal').modal('hide');
    document.getElementById('wishReceiverId').value = userId;
    document.getElementById('wishType').value = wishType;
    document.getElementById('sendWishModalTitle').innerText = (wishType === 'birthday' ? 'Wish ' : 'Congratulate ') + userName;
    
    const message = wishType === 'birthday' 
        ? 'Happy Birthday ' + userName + '! Hope you have a great day ahead.' 
        : 'Happy Work Anniversary ' + userName + '! Congratulations on your milestone and thank you for being a great teammate.';
        
    document.getElementById('wishMessage').value = message;
    
    var myModal = new bootstrap.Modal(document.getElementById('sendWishModal'));
    myModal.show();
}

function submitWish(e) {
    e.preventDefault();
    let btn = document.getElementById('sendWishBtn');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
    btn.disabled = true;
    
    let formData = new FormData(document.getElementById('sendWishForm'));
    
    $.ajax({
        url: "{{ route('birthdays.send') }}",
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            $('#sendWishModal').modal('hide');
            if(typeof toastr !== 'undefined') {
                toastr.success('Your wish has been sent!');
            }
        },
        error: function(xhr) {
            if(typeof toastr !== 'undefined') {
                toastr.error('Failed to send wish.');
            }
        },
        complete: function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

function markWishesRead() {
    $.ajax({
        url: "{{ route('birthdays.markRead') }}",
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        }
    });
}

function triggerConfetti() {
    var duration = 3 * 1000;
    var end = Date.now() + duration;

    (function frame() {
        confetti({
            particleCount: 5,
            angle: 60,
            spread: 55,
            origin: { x: 0 },
            colors: ['#e11d48', '#fecdd3', '#fb7185']
        });
        confetti({
            particleCount: 5,
            angle: 120,
            spread: 55,
            origin: { x: 1 },
            colors: ['#e11d48', '#fecdd3', '#fb7185']
        });

        if (Date.now() < end) {
            requestAnimationFrame(frame);
        }
    }());
}
</script>
