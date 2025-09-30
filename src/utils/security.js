const crypto = require('crypto');

/**
 * Проверка подписи webhook от Lark
 */
function verifySignature(headers, body) {
  const signature = headers['x-lark-signature'];
  const timestamp = headers['x-lark-timestamp'];
  const nonce = headers['x-lark-nonce'];
  
  if (!signature || !timestamp || !nonce) {
    return false;
  }

  const verificationToken = process.env.WEBHOOK_VERIFICATION_TOKEN;
  if (!verificationToken) {
    console.warn('No verification token configured');
    return true; // Если токен не настроен, пропускаем проверку
  }

  // Создаем строку для подписи
  const stringToSign = timestamp + nonce + verificationToken + JSON.stringify(body);
  
  // Вычисляем HMAC-SHA256
  const expectedSignature = crypto
    .createHmac('sha256', verificationToken)
    .update(stringToSign)
    .digest('base64');

  return signature === expectedSignature;
}

/**
 * Генерация безопасного токена
 */
function generateSecureToken(length = 32) {
  return crypto.randomBytes(length).toString('hex');
}

/**
 * Хеширование пароля или токена
 */
function hashString(input, algorithm = 'sha256') {
  return crypto.createHash(algorithm).update(input).digest('hex');
}

/**
 * Проверка валидности URL
 */
function isValidUrl(string) {
  try {
    new URL(string);
    return true;
  } catch (_) {
    return false;
  }
}

/**
 * Санитизация пользовательского ввода
 */
function sanitizeInput(input) {
  if (typeof input !== 'string') {
    return '';
  }
  
  return input
    .trim()
    .replace(/[<>]/g, '') // Удаляем потенциально опасные символы
    .substring(0, 1000); // Ограничиваем длину
}

module.exports = {
  verifySignature,
  generateSecureToken,
  hashString,
  isValidUrl,
  sanitizeInput
};
