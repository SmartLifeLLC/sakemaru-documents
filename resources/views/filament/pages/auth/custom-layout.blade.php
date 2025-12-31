<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Noto Sans JP -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Noto Sans JP"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            500: '#795548', // Brown 500
                            600: '#5D4037', // Brown 700
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Background */
        .bg-image {
            background-image: url('{{ asset('images/background.jpg') }}');
            background-size: cover;
            background-position: center;
        }
        
        /* Input Autofill Background Fix */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        /* Video Overlay Styles */
        #video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            z-index: 9999;
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        #intro-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
    @livewireStyles
</head>
<body class="bg-image min-h-screen flex items-center justify-center font-sans text-gray-800 relative">
    
    <!-- Background Overlay -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm z-0"></div>

    <!-- Video Overlay -->
    <div id="video-overlay">
        <video id="intro-video" playsinline muted>
            <source src="{{ asset('movies/login-movie.mp4') }}" type="video/mp4">
        </video>
    </div>

    {{ $slot }}

    @livewireScripts

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('login-success', (event) => {
                // Handle both array and object event arguments
                const targetUrl = event.url || (Array.isArray(event) && event[0]?.url);

                const loginContainer = document.getElementById('login-container'); // Ensure this ID exists in the child view
                const videoOverlay = document.getElementById('video-overlay');
                const video = document.getElementById('intro-video');
                
                if (!targetUrl) {
                    console.error('Target URL not found in event', event);
                    return;
                }

                // Hide login
                if (loginContainer) {
                    loginContainer.style.transition = 'opacity 0.5s ease';
                    loginContainer.style.opacity = '0';
                    setTimeout(() => {
                        loginContainer.style.display = 'none';
                    }, 500);
                }
                
                // Show video immediately (or after 500ms if you prefer waiting for fade out)
                setTimeout(() => {
                    videoOverlay.style.display = 'flex';
                    // Trigger reflow
                    videoOverlay.offsetHeight;
                    videoOverlay.style.opacity = '1';
                    
                    video.play().then(() => {
                        // Video playing
                    }).catch(e => {
                        console.error("Video play failed", e);
                        window.location.href = targetUrl;
                    });
                    
                    video.onended = function() {
                        window.location.href = targetUrl;
                    };
                }, 500);
            });
        });
    </script>
</body>
</html>
