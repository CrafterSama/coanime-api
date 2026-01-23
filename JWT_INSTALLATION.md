# Instrucciones para completar la migración a JWT

## Pasos que debes ejecutar manualmente

### 1. Instalar el paquete JWT

Ejecuta en tu terminal (dentro del proyecto):

```bash
composer require tymon/jwt-auth
```

### 2. Publicar la configuración de JWT

```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

### 3. Generar la clave secreta de JWT

```bash
php artisan jwt:secret
```

Este comando generará una clave secreta en tu archivo `.env` como `JWT_SECRET`.

## Cambios realizados automáticamente

✅ Modelo `User` actualizado para implementar `JWTSubject`
✅ Guard `api` configurado con driver `jwt` en `config/auth.php`
✅ Controladores de autenticación actualizados para generar tokens JWT
✅ Rutas API actualizadas para usar `auth:api`
✅ Middleware de Sanctum removido del Kernel
✅ `LoginRequest` actualizado para usar guard `api`

## Uso en el Frontend (Next.js)

### Ejemplo de login:

```typescript
const response = await axios.post('/api/login', {
  email: 'user@example.com',
  password: 'password'
});

// Almacenar el token
localStorage.setItem('token', response.data.access_token);

// Configurar axios para usar el token
axios.defaults.headers.common['Authorization'] = `Bearer ${localStorage.getItem('token')}`;
```

### Ejemplo de registro:

```typescript
const response = await axios.post('/api/register', {
  name: 'John Doe',
  email: 'john@example.com',
  password: 'password',
  password_confirmation: 'password'
});

// El token viene en la respuesta
localStorage.setItem('token', response.data.access_token);
```

### Ejemplo de logout:

```typescript
await axios.post('/api/logout');
localStorage.removeItem('token');
delete axios.defaults.headers.common['Authorization'];
```

## Respuesta de Login/Register

Ahora las respuestas incluyen:

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [...]
  }
}
```

## Notas importantes

- Ya no necesitas manejar cookies CSRF
- El token debe enviarse en el header `Authorization: Bearer {token}`
- El token expira según la configuración en `config/jwt.php` (por defecto 60 minutos)
- Puedes refrescar el token usando el endpoint correspondiente si lo implementas
