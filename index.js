import "dotenv/config";

import { sendTelegramMessage } from "./telegram.js";

sendTelegramMessage("✅ Telegram message from server")
  .then(() => console.log("Message sent"))
  .catch(console.error);
