const fs = require('fs');

const extraErrors = {
  fr: {
    user_rejected: "Action annulée par l'utilisateur.",
    insufficient_funds: "Fonds insuffisants. Veuillez recharger votre portefeuille.",
    nonce_too_low: "Conflit de transaction. Essayez de réinitialiser votre portefeuille MetaMask.",
    network_issue: "Problème de réseau détecté. Une simple nouvelle tentative règle souvent le problème !",
    generic_error: "Une erreur inattendue est survenue. Veuillez réessayer ou rafraîchir la page.",
    auth_missing: "Non connecté. Veuillez lier votre portefeuille d'abord."
  },
  en: {
    user_rejected: "Action canceled by user.",
    insufficient_funds: "Insufficient funds. Please top up your wallet.",
    nonce_too_low: "Transaction conflict. Try resetting your MetaMask wallet.",
    network_issue: "Network issue detected. A simple retry often fixes the problem!",
    generic_error: "An unexpected error occurred. Please try again or refresh the page.",
    auth_missing: "Not logged in. Please connect your wallet first."
  },
  es: {
    user_rejected: "Acción cancelada por el usuario.",
    insufficient_funds: "Fondos insuficientes. Por favor recarga tu billetera.",
    nonce_too_low: "Conflicto de transacción. Intenta reiniciar tu billetera MetaMask.",
    network_issue: "Problema de red detectado. ¡Un simple reintento a menudo soluciona el problema!",
    generic_error: "Ocurrió un error inesperado. Por favor intenta de nuevo o recarga la página.",
    auth_missing: "No has iniciado sesión. Por favor conecta tu billetera primero."
  },
  de: {
    user_rejected: "Aktion vom Benutzer abgebrochen.",
    insufficient_funds: "Unzureichendes Guthaben. Bitte laden Sie Ihre Wallet auf.",
    nonce_too_low: "Transaktionskonflikt. Versuchen Sie, Ihre MetaMask-Wallet zurückzusetzen.",
    network_issue: "Netzwerkproblem erkannt. Ein einfacher erneuter Versuch behebt oft das Problem!",
    generic_error: "Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut oder laden Sie die Seite neu.",
    auth_missing: "Nicht angemeldet. Bitte verbinden Sie zuerst Ihre Wallet."
  },
  pt: {
    user_rejected: "Ação cancelada pelo usuário.",
    insufficient_funds: "Fundos insuficientes. Por favor, recarregue sua carteira.",
    nonce_too_low: "Conflito de transação. Tente redefinir sua carteira MetaMask.",
    network_issue: "Problema de rede detectado. Uma simples nova tentativa geralmente resolve o problema!",
    generic_error: "Ocorreu um erro inesperado. Por favor, tente novamente ou atualize a página.",
    auth_missing: "Não conectado. Por favor, conecte sua carteira primeiro."
  },
  zh: {
    user_rejected: "操作已被用户取消。",
    insufficient_funds: "余额不足。请充值您的钱包。",
    nonce_too_low: "交易冲突。请尝试重置您的 MetaMask 钱包。",
    network_issue: "检测到网络问题。简单的重试通常就能解决问题！",
    generic_error: "发生了意外错误。请重试或刷新页面。",
    auth_missing: "未登录。请先连接您的钱包。"
  }
};

for (const lang of Object.keys(extraErrors)) {
  const filePath = `public/locales/${lang}.json`;
  if (fs.existsSync(filePath)) {
    let json = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    if (!json.errors) json.errors = {};
    Object.assign(json.errors, extraErrors[lang]);
    
    fs.writeFileSync(filePath, JSON.stringify(json, null, 2));
    console.log(`Updated extra errors in ${lang}.json`);
  }
}
