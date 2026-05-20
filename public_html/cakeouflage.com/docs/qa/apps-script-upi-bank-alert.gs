// Google Apps Script: UPI Bank Alert Parser + Ping API
// Deploy as Web App and configure Script Properties:
// - APP_SHARED_SECRET: shared secret used by backend test and fetch calls
// - BANK_SENDERS: comma-separated sender allowlist, e.g. alerts@hdfcbank.com,alerts@icicibank.com

const VERSION = '1.0.0';

function doGet(e) {
  return jsonResponse({
    success: true,
    message: 'Apps Script UTR service running',
    version: VERSION,
    now: new Date().toISOString()
  });
}

function doPost(e) {
  try {
    const payload = safeJson(e && e.postData ? e.postData.contents : '{}');
    const action = String(payload.action || '').toLowerCase();

    if (action === 'ping') {
      validateSharedSecret(payload.shared_secret);
      return jsonResponse({
        success: true,
        message: 'pong',
        version: VERSION,
        now: new Date().toISOString()
      });
    }

    if (action === 'fetch_alerts') {
      validateSharedSecret(payload.shared_secret);

      const maxItems = clampInt(payload.max_items, 1, 50, 20);
      const lookbackMinutes = clampInt(payload.lookback_minutes, 5, 1440, 180);
      const senderCsv = String(getProp('BANK_SENDERS', '')).trim();
      const senders = senderCsv ? senderCsv.split(',').map(s => s.trim().toLowerCase()).filter(Boolean) : [];

      const alerts = collectBankAlerts({ maxItems, lookbackMinutes, senders });

      return jsonResponse({
        success: true,
        message: 'ok',
        count: alerts.length,
        data: alerts
      });
    }

    return jsonResponse({ success: false, message: 'Unsupported action' }, 400);
  } catch (err) {
    return jsonResponse({
      success: false,
      message: err && err.message ? err.message : 'Unexpected error'
    }, 500);
  }
}

function collectBankAlerts(opts) {
  const maxItems = opts.maxItems;
  const lookbackMinutes = opts.lookbackMinutes;
  const senders = opts.senders || [];

  const queryParts = [];
  queryParts.push('newer_than:' + Math.ceil(lookbackMinutes / 60) + 'h');
  if (senders.length > 0) {
    const senderQuery = senders.map(s => 'from:' + s).join(' OR ');
    queryParts.push('(' + senderQuery + ')');
  }

  const threads = GmailApp.search(queryParts.join(' '), 0, 100);
  const out = [];

  for (let t = 0; t < threads.length; t++) {
    const msgs = threads[t].getMessages();
    for (let i = msgs.length - 1; i >= 0; i--) {
      const m = msgs[i];
      const date = m.getDate();
      const ageMs = Date.now() - date.getTime();
      if (ageMs > lookbackMinutes * 60 * 1000) {
        continue;
      }

      const from = String(m.getFrom() || '').toLowerCase();
      if (senders.length > 0 && !senders.some(s => from.indexOf(s) >= 0)) {
        continue;
      }

      const subject = String(m.getSubject() || '');
      const body = normalizeText(m.getPlainBody() || m.getBody() || '');
      const utr = extractUtr(body, subject);
      const amount = extractAmount(body, subject);

      if (!utr || amount === null) {
        continue;
      }

      out.push({
        message_id: m.getId(),
        thread_id: threads[t].getId(),
        sender: from,
        subject: subject.substring(0, 250),
        received_at: date.toISOString(),
        parsed_utr: utr,
        parsed_amount: amount,
        raw_snippet: body.substring(0, 1200)
      });

      if (out.length >= maxItems) {
        return out;
      }
    }
  }

  return out;
}

function extractUtr(body, subject) {
  const text = (body + ' ' + subject).toUpperCase();
  const patterns = [
    /(?:UTR|REF(?:ERENCE)?|RRN|TXN(?: ID)?)[^A-Z0-9]{0,8}([A-Z0-9]{12,22})/i,
    /\b([0-9]{12})\b/
  ];

  for (let i = 0; i < patterns.length; i++) {
    const m = text.match(patterns[i]);
    if (m && m[1]) {
      const candidate = m[1].replace(/[^A-Z0-9]/g, '').toUpperCase();
      if (candidate.length >= 12) {
        return candidate;
      }
    }
  }

  return null;
}

function extractAmount(body, subject) {
  const text = (body + ' ' + subject).replace(/,/g, '');
  const patterns = [
    /(?:INR|RS\.?|₹)\s*([0-9]+(?:\.[0-9]{1,2})?)/i,
    /(?:CREDIT(?:ED)?|RECEIVED)[^0-9]{0,20}([0-9]+(?:\.[0-9]{1,2})?)/i
  ];

  for (let i = 0; i < patterns.length; i++) {
    const m = text.match(patterns[i]);
    if (m && m[1]) {
      const val = parseFloat(m[1]);
      if (!isNaN(val) && val > 0) {
        return Number(val.toFixed(2));
      }
    }
  }

  return null;
}

function validateSharedSecret(incomingSecret) {
  const secret = getProp('APP_SHARED_SECRET', '');
  if (!secret) {
    throw new Error('APP_SHARED_SECRET is not configured');
  }
  if (String(incomingSecret || '') !== String(secret)) {
    throw new Error('Unauthorized shared secret');
  }
}

function normalizeText(htmlOrText) {
  return String(htmlOrText || '')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function safeJson(raw) {
  try {
    return JSON.parse(raw || '{}');
  } catch (e) {
    return {};
  }
}

function getProp(key, fallback) {
  const value = PropertiesService.getScriptProperties().getProperty(key);
  return value === null || value === undefined || value === '' ? fallback : value;
}

function clampInt(v, min, max, fallback) {
  const n = parseInt(v, 10);
  if (isNaN(n)) {
    return fallback;
  }
  return Math.max(min, Math.min(max, n));
}

function jsonResponse(obj, code) {
  const out = ContentService.createTextOutput(JSON.stringify(obj));
  out.setMimeType(ContentService.MimeType.JSON);
  return out;
}
