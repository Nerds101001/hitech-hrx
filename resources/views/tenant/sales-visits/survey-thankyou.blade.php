<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Thank You for Your Feedback! | Hi Tech HRX</title>

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
        .thankyou-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 77, 84, 0.08);
            border: none;
            max-width: 500px;
            width: 100%;
            padding: 3.5rem 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .thankyou-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #0d7377, #14a085);
        }
        .icon-check {
            width: 72px;
            height: 72px;
            background: #e0f2f1;
            color: #0d7377;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
        }
        .thankyou-card h2 {
            font-weight: 800;
            color: #005a5a;
            letter-spacing: -0.5px;
            font-size: 1.6rem;
            margin-bottom: 0.75rem;
        }
        .thankyou-card p {
            color: #718096;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .footer-logo {
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #a0aec0;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="thankyou-card">
    <div class="icon-check">✓</div>
    <h2>Thank You!</h2>
    <p>Your feedback has been successfully submitted. We appreciate you taking the time to share your experience with us.</p>
    
    <div class="footer-logo">
        Hi Tech HRX
    </div>
</div>

</body>
</html>
