<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #020617; color: white; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="text-center">
        <div class="inline-block p-6 rounded-full bg-blue-500/10 mb-6 animate-pulse">
            <i class="fas fa-plane-departure text-4xl text-blue-500"></i>
        </div>
        <h1 class="text-2xl font-black uppercase tracking-widest mb-2">Safe Travels</h1>
        <p class="text-slate-500">Disconnecting from Altitude X Terminal...</p>
    </div>

    <script>
        // Smoothly redirect after 1.5 seconds
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 1500);
    </script>
</body>
</html>