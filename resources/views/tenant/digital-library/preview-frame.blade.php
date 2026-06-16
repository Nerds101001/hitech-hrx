<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Video Preview</title>
    <style>
        body, html { 
            margin: 0; 
            padding: 0; 
            height: 100%; 
            width: 100%; 
            overflow: hidden; 
            background: #000; 
        }
        iframe { 
            width: 100%; 
            height: 100%; 
            border: none; 
        }
        /* Invisible overlay to prevent clicks on the Google Drive popout button */
        .header-overlay { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 65px; 
            z-index: 9999; 
            background: transparent; 
        }
    </style>
</head>
<body oncontextmenu="return false;" onkeydown="return (event.keyCode != 123);">
    <div class="header-overlay"></div>
    <iframe src="{{ $embedUrl }}" allowfullscreen allow="autoplay"></iframe>
</body>
</html>
