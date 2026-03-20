import axios from 'axios'

export async function getCsrfCookie() {
  try {
    await axios.get('http://localhost:8000/sanctum/csrf-cookie', {
      withCredentials: true,
    })
  } catch (error) {
    console.log('CSRF cookie fetch skipped')
  }
}
