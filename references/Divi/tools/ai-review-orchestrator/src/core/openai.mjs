import OpenAI from "openai";

export const getOpenAIClient = () => {
  const key = process.env.OPENAI_API_KEY;
  if ("string" !== typeof key || "" === key.trim()) {
    return null;
  }
  return new OpenAI({ apiKey: key });
};
