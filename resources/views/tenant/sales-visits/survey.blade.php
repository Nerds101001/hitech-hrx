<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Visit Feedback Survey | Hi Tech HRX</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            color: #2F3E46;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .survey-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 77, 84, 0.08);
            border: none;
            max-width: 580px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }
        .survey-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #0d7377, #14a085);
        }
        .survey-header {
            padding: 2.5rem 2.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #edf2f0;
        }
        .survey-header h2 {
            font-weight: 800;
            color: #005a5a;
            letter-spacing: -0.5px;
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
        }
        .survey-header p {
            color: #8A90A2;
            font-size: 0.92rem;
            margin-bottom: 0;
        }
        .survey-body {
            padding: 2rem 2.5rem 2.5rem;
        }
        .info-pill {
            background-color: #f0fdfa;
            border: 1px solid #ccfbf1;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            font-size: 0.88rem;
        }
        .info-pill-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0d9488;
            margin-bottom: 0.4rem;
        }
        .info-pill-value {
            font-weight: 600;
            color: #2D3748;
        }
        .emoji-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }
        .emoji-option {
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            flex: 1;
        }
        .emoji-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .emoji-face {
            font-size: 2.8rem;
            display: inline-block;
            transition: transform 0.2s ease, filter 0.2s ease;
            filter: grayscale(40%) opacity(70%);
        }
        .emoji-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: #8a90a2;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: color 0.25s ease;
        }
        .emoji-option:hover .emoji-face {
            transform: scale(1.2);
            filter: grayscale(0%) opacity(100%);
        }
        .emoji-option input:checked ~ .emoji-face {
            transform: scale(1.3);
            filter: grayscale(0%) opacity(100%);
        }
        .emoji-option input:checked ~ .emoji-label {
            color: #0d7377;
            font-weight: 800;
        }
        .comment-section {
            display: none;
            margin-top: 1.5rem;
            animation: fadeIn 0.3s ease-in-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4A5568;
        }
        .form-control {
            border-radius: 12px;
            border: 2px solid #E2E8F0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #0d7377;
            box-shadow: 0 0 0 3px rgba(13, 115, 119, 0.15);
        }
        .btn-submit {
            background: linear-gradient(135deg, #0d7377, #14a085);
            color: #ffffff;
            font-weight: 700;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            width: 100%;
            border: none;
            box-shadow: 0 4px 18px rgba(13, 115, 119, 0.3);
            transition: all 0.25s ease;
            margin-top: 1.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(13, 115, 119, 0.4);
            color: #ffffff;
        }
        .btn-submit:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>

<div class="survey-card">
    <div class="survey-header">
        <h2>Rate Your Experience</h2>
        <p>Your feedback is vital to help us improve our services.</p>
    </div>
    
    <div class="survey-body">
        <!-- Info Summary -->
        <div class="info-pill">
            <div class="row">
                <div class="col-6 mb-2">
                    <div class="info-pill-title">Client</div>
                    <div class="info-pill-value">{{ $visit->client->name }}</div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-pill-title">Representative</div>
                    <div class="info-pill-value">{{ $visit->salesperson->name }}</div>
                </div>
                <div class="col-6">
                    <div class="info-pill-title">Visit Type</div>
                    <div class="info-pill-value">{{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}</div>
                </div>
                <div class="col-6">
                    <div class="info-pill-title">Date</div>
                    <div class="info-pill-value">{{ $visit->completed_at ? $visit->completed_at->format('d M Y') : '' }}</div>
                </div>
            </div>
        </div>

        <form action="{{ route('sales-visits.survey.submit', $visit->survey_token) }}" method="POST" id="surveyForm">
            @csrf
            
            <div class="text-center mb-3">
                <label class="form-label mb-3 fs-6">How would you rate the meeting?</label>
            </div>
            
            <!-- Emojis -->
            <div class="emoji-container">
                <label class="emoji-option" onclick="handleEmojiClick(1)">
                    <input type="radio" name="rating" value="1" required>
                    <span class="emoji-face">😠</span>
                    <span class="emoji-label">Terrible</span>
                </label>
                <label class="emoji-option" onclick="handleEmojiClick(2)">
                    <input type="radio" name="rating" value="2">
                    <span class="emoji-face">🙁</span>
                    <span class="emoji-label">Bad</span>
                </label>
                <label class="emoji-option" onclick="handleEmojiClick(3)">
                    <input type="radio" name="rating" value="3">
                    <span class="emoji-face">😐</span>
                    <span class="emoji-label">Okay</span>
                </label>
                <label class="emoji-option" onclick="handleEmojiClick(4)">
                    <input type="radio" name="rating" value="4">
                    <span class="emoji-face">🙂</span>
                    <span class="emoji-label">Good</span>
                </label>
                <label class="emoji-option" onclick="handleEmojiClick(5)">
                    <input type="radio" name="rating" value="5">
                    <span class="emoji-face">😀</span>
                    <span class="emoji-label">Great</span>
                </label>
            </div>

            <!-- Comments Box (Triggered for rating <= 3) -->
            <div class="comment-section" id="commentSection">
                <label class="form-label mb-2" for="ratingComment">Please let us know what we can improve <span class="text-danger">*</span></label>
                <textarea class="form-control" name="rating_comment" id="ratingComment" rows="4" placeholder="Share your experience here..."></textarea>
            </div>

            <button type="submit" class="btn btn-submit" id="submitBtn" disabled>
                Submit Feedback
            </button>
        </form>
    </div>
</div>

<script>
    function handleEmojiClick(rating) {
        const commentSection = document.getElementById('commentSection');
        const commentInput = document.getElementById('ratingComment');
        const submitBtn = document.getElementById('submitBtn');

        submitBtn.disabled = false;

        if (rating <= 3) {
            commentSection.style.display = 'block';
            commentInput.required = true;
        } else {
            commentSection.style.display = 'none';
            commentInput.required = false;
            commentInput.value = '';
        }
    }
</script>
</body>
</html>
