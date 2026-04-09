@props([
    'title' => 'Dashboard',
    'subtitle' => 'Welcome back!',
    'icon' => 'bx-home',
    'gradient' => 'primary',
    'quote' => null
])

@php
    $gradients = [
        'primary' => 'linear-gradient(135deg, #004d4d 0%, #007a7a 60%, #00a3a3 100%)',
        'success' => 'linear-gradient(135deg, #005a5a 0%, #008a8a 100%)',
        'info' => 'linear-gradient(135deg, #006666 0%, #009999 100%)',
        'warning' => 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)',
        'danger' => 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)',
        'teal' => 'linear-gradient(135deg, #004d4d 0%, #007a7a 100%)'
    ];
    
    $bgGradient = $gradients[$gradient] ?? $gradients['primary'];
    
    $quotes = [
        'Success is the result of hard work, determination, and courage to pursue greatness.',
        'Teamwork divides the task and multiplies the success.',
        'Productivity is not about doing more; it\'s about focusing on what truly matters.',
        'The secret to great teamwork is trust, communication, and a shared goal.',
        'Every accomplishment starts with the decision to try.',
        'Productivity is never an accident. It is always the result of commitment to excellence.',
        'Believe in your ability to shape the future with the work you do today.',
        'Efficiency is doing things right; effectiveness is doing the right things.',
        'Dream big, work hard, stay focused, and surround yourself with good people.',
        'The best way to predict the future is to create it.'
    ];
    
    $quoteText = $quote ?? $quotes[array_rand($quotes)];
@endphp

<div class="admin-hero animate__animated animate__fadeIn" style="background: {{ $bgGradient }};">
    <div class="admin-hero-content">
        <div class="admin-hero-text">
            <div class="hero-icon">
                <i class="bx {{ $icon }} text-white"></i>
            </div>
            <div class="greeting">{{ $title }}</div>
            <div class="sub-text">{{ $subtitle }}</div>
        </div>
        @if($quote)
            <div class="d-none d-md-block text-end" style="max-width: 420px; position:relative; z-index:1;">
                <small class="text-white opacity-75 fst-italic">"{{ $quoteText }}"</small>
            </div>
        @endif
    </div>
</div>
