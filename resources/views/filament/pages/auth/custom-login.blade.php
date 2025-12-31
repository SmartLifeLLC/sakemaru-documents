<!-- Main Card -->
<div id="login-container" class="relative z-10 w-full max-w-md p-6 mx-4 animate-fade-in">
    
    <!-- Card Body: Glassmorphism -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/20">
        
        <div class="p-8">
            <!-- Logo Area -->
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo-small.jpg') }}" alt="酒丸帳場" class="mx-auto mb-4 h-12">
                <h2 class="text-2xl font-bold text-gray-800">おかえりなさい</h2>
                <p class="text-sm text-gray-500 mt-2">アカウントにログインして続行してください</p>
            </div>

            <!-- Login Form -->
            <form wire:submit="authenticate" class="space-y-6">
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" wire:model="data.email" required 
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors bg-white/50 focus:bg-white placeholder-gray-400" 
                            placeholder="name@example.com">
                    </div>
                    @error('data.email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-sm font-medium text-gray-700">パスワード</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" wire:model="data.password" required 
                            class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors bg-white/50 focus:bg-white placeholder-gray-400" 
                            placeholder="••••••••">
                        <!-- Password Toggle Button -->
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer focus:outline-none">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    @error('data.password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input type="checkbox" id="remember" wire:model="data.remember" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-900">ログイン状態を保持する</label>
                </div>

                <!-- Login Button -->
                <button type="submit" 
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-gradient-to-r from-brand-500 to-brand-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="authenticate">ログイン</span>
                    <span wire:loading wire:target="authenticate"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> 認証中...</span>
                </button>
            </form>
            
            @if ($errors->has('data.login'))
                <div class="mt-4 text-center text-red-500 text-sm">
                    {{ $errors->first('data.login') }}
                </div>
            @endif
        </div>
        
        <!-- Copyright -->
        <div class="bg-gray-50 px-8 py-4 text-center">
             <p class="text-xs text-gray-500">&copy; {{ date('Y') }} Smart Life LLC. All rights reserved.</p>
        </div>
    </div>
</div>

<script>
    // Password Toggle Logic
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');
    const eyeIcon = togglePassword.querySelector('i');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Icon Toggle
        if(type === 'text') {
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    });
</script>
