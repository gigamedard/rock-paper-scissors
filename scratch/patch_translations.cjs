const fs = require('fs');

const data = {
  fr: {
    errors: {
      metamask_required: "MetaMask est requis !",
      auth_failed: "Échec de l'authentification : ",
      claim_previous: "Veuillez réclamer vos gains de la session précédente avant d'en démarrer une nouvelle.",
      cooldown_active: "Temps de recharge actif. Veuillez patienter encore :remaining secondes.",
      select_premoves: "Veuillez sélectionner 10 pré-mouvements !",
      error_prefix: "Erreur : ",
      claim_failed: "Échec de la réclamation : "
    },
    marketplace_error: "Erreur lors du chargement du marché."
  },
  en: {
    errors: {
      metamask_required: "MetaMask is required!",
      auth_failed: "Authentication failed: ",
      claim_previous: "Please claim your winnings from the previous session before starting a new one.",
      cooldown_active: "Cooldown active. Please wait another :remaining seconds.",
      select_premoves: "Please select 10 pre-moves!",
      error_prefix: "Error: ",
      claim_failed: "Claim failed: "
    },
    marketplace_error: "Error loading marketplace."
  },
  es: {
    errors: {
      metamask_required: "¡Se requiere MetaMask!",
      auth_failed: "Autenticación fallida: ",
      claim_previous: "Por favor, reclama tus ganancias de la sesión anterior antes de comenzar una nueva.",
      cooldown_active: "Tiempo de espera activo. Por favor, espera :remaining segundos más.",
      select_premoves: "¡Por favor selecciona 10 pre-movimientos!",
      error_prefix: "Error: ",
      claim_failed: "Reclamo fallido: "
    },
    marketplace_error: "Error al cargar el mercado."
  },
  de: {
    errors: {
      metamask_required: "MetaMask ist erforderlich!",
      auth_failed: "Authentifizierung fehlgeschlagen: ",
      claim_previous: "Bitte beanspruchen Sie Ihre Gewinne aus der vorherigen Sitzung, bevor Sie eine neue beginnen.",
      cooldown_active: "Abklingzeit aktiv. Bitte warten Sie noch :remaining Sekunden.",
      select_premoves: "Bitte wählen Sie 10 Voreinstellungen aus!",
      error_prefix: "Fehler: ",
      claim_failed: "Beanspruchung fehlgeschlagen: "
    },
    marketplace_error: "Fehler beim Laden des Marktplatzes."
  },
  pt: {
    errors: {
      metamask_required: "MetaMask é necessário!",
      auth_failed: "Falha na autenticação: ",
      claim_previous: "Por favor, resgate seus ganhos da sessão anterior antes de iniciar uma nova.",
      cooldown_active: "Tempo de espera ativo. Por favor, aguarde mais :remaining segundos.",
      select_premoves: "Por favor, selecione 10 pré-jogadas!",
      error_prefix: "Erro: ",
      claim_failed: "Falha no resgate: "
    },
    marketplace_error: "Erro ao carregar o mercado."
  },
  zh: {
    errors: {
      metamask_required: "需要 MetaMask！",
      auth_failed: "身份验证失败：",
      claim_previous: "在开始新会话之前，请先领取上一会话的奖金。",
      cooldown_active: "冷却时间激活。请再等待 :remaining 秒。",
      select_premoves: "请选择 10 个预移动！",
      error_prefix: "错误：",
      claim_failed: "领取失败："
    },
    marketplace_error: "加载市场时出错。"
  }
};

for (const lang of Object.keys(data)) {
  const filePath = `public/locales/${lang}.json`;
  if (fs.existsSync(filePath)) {
    let json = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    // Add errors block
    json.errors = data[lang].errors;
    
    // Add marketplace.error_loading
    if (json.marketplace) {
      json.marketplace.error_loading = data[lang].marketplace_error;
    }
    
    fs.writeFileSync(filePath, JSON.stringify(json, null, 2));
    console.log(`Updated ${lang}.json`);
  }
}
