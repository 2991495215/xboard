import { createHash, timingSafeEqual } from "node:crypto";
import { readFileSync } from "node:fs";
import { createServer } from "node:http";
import { WaffoPancake, WebhookEventType } from "@waffo/pancake-ts";

const required = [
  "WAFFO_MERCHANT_ID",
  "WAFFO_STORE_ID",
  "WAFFO_PRODUCT_ID",
  "WAFFO_PRIVATE_KEY_FILE",
  "WAFFO_BRIDGE_TOKEN",
];
for (const name of required) {
  if (!process.env[name]) throw new Error(`${name} is required`);
}

const environment = process.env.WAFFO_ENVIRONMENT || "test";
if (!new Set(["test", "prod"]).has(environment)) throw new Error("Invalid WAFFO_ENVIRONMENT");

const storeId = process.env.WAFFO_STORE_ID;
const productId = process.env.WAFFO_PRODUCT_ID;
const token = Buffer.from(process.env.WAFFO_BRIDGE_TOKEN);
const client = new WaffoPancake({
  merchantId: process.env.WAFFO_MERCHANT_ID,
  privateKey: readFileSync(process.env.WAFFO_PRIVATE_KEY_FILE, "utf8"),
  environment,
});

function send(response, status, body) {
  response.writeHead(status, { "content-type": "application/json; charset=utf-8" });
  response.end(JSON.stringify(body));
}

function authorized(request) {
  const value = request.headers.authorization || "";
  const candidate = Buffer.from(value.startsWith("Bearer ") ? value.slice(7) : "");
  return candidate.length === token.length && timingSafeEqual(candidate, token);
}

async function readJson(request) {
  let body = "";
  for await (const chunk of request) {
    body += chunk;
    if (body.length > 1_000_000) throw new Error("Request body too large");
  }
  return JSON.parse(body || "{}");
}

function amountFromCents(value) {
  if (!Number.isInteger(value) || value < 1) throw new Error("Invalid total_amount");
  return (value / 100).toFixed(2);
}

async function checkout(input) {
  const tradeNo = String(input.trade_no || "");
  const userId = String(input.user_id || "");
  const returnUrl = String(input.return_url || "");
  if (!tradeNo || !userId || !returnUrl.startsWith("https://")) throw new Error("Invalid checkout request");

  return client.checkout.authenticated.create(
    {
      productId,
      currency: "CNY",
      buyerIdentity: `xboard:${userId}`,
      priceSnapshot: { amount: amountFromCents(input.total_amount), taxCategory: "digital_goods" },
      successUrl: returnUrl,
      orderMerchantExternalId: tradeNo,
      language: "zh-Hans",
      metadata: { trade_no: tradeNo, xboard_user_id: userId },
    },
    { idempotencyKey: `xboard_${createHash("sha256").update(tradeNo).digest("hex")}` },
  );
}

function verifyWebhook(input) {
  const event = client.webhooks.verify(String(input.raw_body || ""), String(input.signature || ""), { environment });
  if (event.storeId !== storeId || event.mode !== environment) throw new Error("Webhook scope mismatch");
  if (event.eventType !== WebhookEventType.OrderCompleted) return { paid: false };

  const tradeNo = event.data.orderMerchantExternalId;
  const callbackNo = event.data.paymentId || event.eventId;
  if (!tradeNo || !callbackNo) throw new Error("Webhook identifiers missing");
  return { paid: true, trade_no: tradeNo, callback_no: callbackNo };
}

const server = createServer(async (request, response) => {
  try {
    if (request.method === "GET" && request.url === "/health") {
      return send(response, 200, { ok: true, environment, storeId, productId });
    }
    if (!authorized(request)) return send(response, 401, { error: "Unauthorized" });
    const input = await readJson(request);
    if (request.method === "POST" && request.url === "/checkout") {
      const result = await checkout(input);
      return send(response, 200, { checkout_url: result.checkoutUrl });
    }
    if (request.method === "POST" && request.url === "/verify-webhook") {
      return send(response, 200, verifyWebhook(input));
    }
    return send(response, 404, { error: "Not found" });
  } catch (error) {
    console.error(error?.message || error);
    return send(response, 400, { error: "Request failed" });
  }
});

server.listen(18792, "127.0.0.1", () => console.log(`Waffo bridge listening on 127.0.0.1:18792 (${environment})`));
