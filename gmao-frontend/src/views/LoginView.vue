<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('admin@gmao.com')
const password = ref('password')
const error = ref('')
const showPassword = ref(false)

async function handleLogin() {
  error.value = ''
  const result = await authStore.login(email.value, password.value)
  
  if (result.success) {
    router.push('/')
  } else {
    error.value = result.message
  }
}
</script>

<template>
  <div class="login-container">
    <!-- Animated background shapes -->
    <div class="bg-shapes">
      <div class="shape shape-1"></div>
      <div class="shape shape-2"></div>
      <div class="shape shape-3"></div>
    </div>

    <div class="login-card">
      <!-- Logo/Icon -->
      <div class="logo-container">
        <div class="logo-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
          </svg>
        </div>
      </div>

      <h1>GMAO</h1>
      <p class="subtitle">Gestion de Maintenance Assistée par Ordinateur</p>
      
      <form @submit.prevent="handleLogin">
        <!-- Error Alert -->
        <transition name="shake">
          <div class="alert alert-error" v-if="error">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <span>{{ error }}</span>
          </div>
        </transition>
        
        <!-- Email Field -->
        <div class="form-group">
          <label for="email">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
            Email
          </label>
          <div class="input-wrapper">
            <input 
              type="email" 
              id="email" 
              v-model="email" 
              required 
              placeholder="exemple@domaine.com"
              autocomplete="email"
            />
          </div>
        </div>
        
        <!-- Password Field -->
        <div class="form-group">
          <label for="password">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Mot de passe
          </label>
          <div class="input-wrapper password-wrapper">
            <input 
              :type="showPassword ? 'text' : 'password'" 
              id="password" 
              v-model="password" 
              required 
              placeholder="••••••••"
              autocomplete="current-password"
            />
            <button 
              type="button" 
              class="toggle-password"
              @click="showPassword = !showPassword"
              tabindex="-1"
            >
              <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Remember & Forgot -->
        <div class="form-options">
          <label class="checkbox-wrapper">
            <input type="checkbox" />
            <span class="checkmark"></span>
            Se souvenir de moi
          </label>
          <a href="#" class="forgot-link">Mot de passe oublié ?</a>
        </div>
        
        <!-- Submit Button -->
        <button 
          type="submit" 
          class="btn btn-primary btn-block" 
          :disabled="authStore.isLoading"
          :class="{ 'loading': authStore.isLoading }"
        >
          <span class="btn-text">{{ authStore.isLoading ? 'Connexion en cours...' : 'Se connecter' }}</span>
          <div class="spinner" v-if="authStore.isLoading"></div>
        </button>
      </form>

      <!-- Footer -->
      <div class="login-footer">
        <p>© 2026 GMAO - Tous droits réservés</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  position: relative;
  overflow: hidden;
  padding: 20px;
}

/* Animated Background Shapes */
.bg-shapes {
  position: absolute;
  width: 100%;
  height: 100%;
  top: 0;
  left: 0;
  overflow: hidden;
  z-index: 0;
}

.shape {
  position: absolute;
  border-radius: 50%;
  background: linear-gradient(45deg, rgba(66, 133, 244, 0.1), rgba(52, 168, 83, 0.1));
  animation: float 15s infinite ease-in-out;
}

.shape-1 {
  width: 400px;
  height: 400px;
  top: -100px;
  right: -100px;
  animation-delay: 0s;
}

.shape-2 {
  width: 300px;
  height: 300px;
  bottom: -50px;
  left: -50px;
  animation-delay: -5s;
}

.shape-3 {
  width: 200px;
  height: 200px;
  top: 50%;
  left: 50%;
  animation-delay: -10s;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0) rotate(0deg);
    opacity: 0.5;
  }
  50% {
    transform: translateY(-30px) rotate(180deg);
    opacity: 0.8;
  }
}

/* Login Card */
.login-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  padding: 50px 40px;
  border-radius: 24px;
  box-shadow: 
    0 25px 50px -12px rgba(0, 0, 0, 0.25),
    0 0 0 1px rgba(255, 255, 255, 0.1);
  width: 100%;
  max-width: 420px;
  position: relative;
  z-index: 1;
  animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Logo */
.logo-container {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.logo-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #3498db, #2c3e50);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.logo-icon:hover {
  transform: scale(1.05) rotate(5deg);
  box-shadow: 0 15px 40px rgba(52, 152, 219, 0.4);
}

/* Typography */
.login-card h1 {
  text-align: center;
  color: #1a1a2e;
  margin-bottom: 8px;
  font-size: 32px;
  font-weight: 700;
  letter-spacing: -0.5px;
}

.subtitle {
  text-align: center;
  color: #64748b;
  margin-bottom: 35px;
  font-size: 14px;
  font-weight: 400;
}

/* Form Groups */
.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 8px;
}

.form-group label svg {
  color: #64748b;
}

.input-wrapper {
  position: relative;
}

.form-group input {
  width: 100%;
  padding: 14px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 15px;
  transition: all 0.3s ease;
  background: #f8fafc;
  color: #1e293b;
}

.form-group input:focus {
  outline: none;
  border-color: #3498db;
  background: white;
  box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
}

.form-group input::placeholder {
  color: #94a3b8;
}

/* Password Toggle */
.password-wrapper {
  position: relative;
}

.toggle-password {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: #94a3b8;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.3s ease;
}

.toggle-password:hover {
  color: #3498db;
}

.password-wrapper input {
  padding-right: 50px;
}

/* Form Options */
.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
  font-size: 13px;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: #64748b;
  position: relative;
  padding-left: 28px;
}

.checkbox-wrapper input {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.checkmark {
  position: absolute;
  left: 0;
  height: 20px;
  width: 20px;
  background-color: #f1f5f9;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.checkbox-wrapper:hover .checkmark {
  border-color: #3498db;
}

.checkbox-wrapper input:checked ~ .checkmark {
  background-color: #3498db;
  border-color: #3498db;
}

.checkmark:after {
  content: "";
  position: absolute;
  display: none;
  left: 6px;
  top: 2px;
  width: 5px;
  height: 10px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.checkbox-wrapper input:checked ~ .checkmark:after {
  display: block;
}

.forgot-link {
  color: #3498db;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s ease;
}

.forgot-link:hover {
  color: #2980b9;
  text-decoration: underline;
}

/* Submit Button */
.btn-block {
  width: 100%;
  padding: 16px 24px;
  font-size: 16px;
  font-weight: 600;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: linear-gradient(135deg, #3498db, #2c3e50);
  color: white;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.btn-block::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
  transition: left 0.5s ease;
}

.btn-block:hover::before {
  left: 100%;
}

.btn-block:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(52, 152, 219, 0.4);
}

.btn-block:active {
  transform: translateY(0);
}

.btn-block:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

/* Loading Spinner */
.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Alert */
.alert-error {
  background: linear-gradient(135deg, #fee2e2, #fecaca);
  border: 1px solid #f87171;
  color: #b91c1c;
  padding: 14px 16px;
  border-radius: 12px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 500;
}

.alert-error svg {
  flex-shrink: 0;
}

/* Shake Animation */
.shake-enter-active {
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

/* Footer */
.login-footer {
  margin-top: 30px;
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.login-footer p {
  color: #94a3b8;
  font-size: 12px;
  margin: 0;
}

/* Responsive */
@media (max-width: 480px) {
  .login-card {
    padding: 40px 24px;
    border-radius: 20px;
  }

  .login-card h1 {
    font-size: 26px;
  }

  .form-options {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }

  .logo-icon {
    width: 60px;
    height: 60px;
  }

  .logo-icon svg {
    width: 32px;
    height: 32px;
  }
}
</style>
