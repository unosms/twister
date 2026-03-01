import axios from "axios";

export async function sendTelegramMessage(text) {
  const token = process.env.TELEGRAM_BOT_TOKEN;
  const chatId = process.env.TELEGRAM_CHAT_ID;

  if (!token || !chatId) {
    throw new Error("Missing TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID");
  }

  const url = `https://api.telegram.org/bot${token}/sendMessage`;

  const res = await axios.post(url, {
    chat_id: chatId,
    text,
    disable_web_page_preview: true,
  });

  return res.data;
}
