//+------------------------------------------------------------------+
//| AI_Manual_Ask.mq5                                                |
//| One-click script: send market data to AI without waiting         |
//| Navigator → Scripts → AI_Manual_Ask → drag onto chart            |
//+------------------------------------------------------------------+
#property copyright "MT5 AI Trading Platform"
#property version   "2.00"
#property script_show_inputs

input string   InpApiBaseUrl      = "https://mt5-ai.niksofts.com/api";
input string   InpApiToken        = "";  // Per-account token from Super Admin
input string   InpSymbols         = "XAUUSD";  // Fallback when admin has none
input ENUM_TIMEFRAMES InpTimeframe = PERIOD_M15;
input int      InpCandleCount     = 50;
input bool     InpUseServerConfig = true;
input bool     InpAllowSymbolFallback = false;
input bool     InpManageOpenPos   = false;  // Also send open positions for management

//+------------------------------------------------------------------+
void OnStart()
{
   if(StringLen(InpApiToken) < 8)
   {
      Alert("Set InpApiToken — generate in Super Admin → Accounts");
      return;
   }

   string symbols[];
   int count = 0;

   if(InpUseServerConfig)
   {
      if(!FetchSymbolsFromServer(symbols, count))
         Print("Warning: Could not load admin config — using fallback if allowed");
   }

   if(count <= 0 && InpAllowSymbolFallback)
      count = ParseSymbols(InpSymbols, symbols);

   if(count <= 0)
   {
      Alert("No symbols configured. Set symbols in Super Admin: /admin/accounts");
      return;
   }

   string tf = TimeframeToString(InpTimeframe);
   string json = "{";
   json += "\"account\":{";
   json += "\"login\":" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN)) + ",";
   json += "\"balance\":" + DoubleToString(AccountInfoDouble(ACCOUNT_BALANCE), 2) + ",";
   json += "\"equity\":" + DoubleToString(AccountInfoDouble(ACCOUNT_EQUITY), 2) + ",";
   json += "\"free_margin\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN_FREE), 2);
   json += "},\"symbols\":[";

   bool first = true;
   for(int i = 0; i < count; i++)
   {
      if(!SymbolInfoInteger(symbols[i], SYMBOL_EXIST))
      {
         Print("Skipping missing symbol: ", symbols[i]);
         continue;
      }
      SymbolSelect(symbols[i], true);
      if(!first) json += ",";
      first = false;
      json += BuildSymbolJson(symbols[i], tf);
   }
   json += "]}";

   string response;
   Print("Sending manual AI entry request...");
   if(HttpPost(InpApiBaseUrl + "/market-data", json, response))
   {
      Print("Success: ", response);
      Alert("AI entry analysis queued. Check signals in ~30-60 sec.");
   }
   else
   {
      Print("Failed: ", response);
      Alert("Failed to send data. Check Experts tab.");
   }

   if(InpManageOpenPos)
      SendAllOpenPositions();
}

//+------------------------------------------------------------------+
bool FetchSymbolsFromServer(string &out[], int &count)
{
   string url = InpApiBaseUrl + "/account-config?account=" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN));
   string response;
   if(!HttpGet(url, response))
      return false;

   bool configured = JsonGetBool(response, "configured", false);
   string symbolsCsv = JsonGetSymbolsCsv(response);

   if(symbolsCsv != "")
      count = ParseSymbols(symbolsCsv, out);
   else if(!configured && InpAllowSymbolFallback)
      count = ParseSymbols(InpSymbols, out);
   else
      count = 0;

   Print("Admin config: configured=", configured ? "yes" : "no", " | symbols=", count);
   return true;
}

//+------------------------------------------------------------------+
void SendAllOpenPositions()
{
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(!PositionSelectByTicket(ticket)) continue;

      string symbol = PositionGetString(POSITION_SYMBOL);
      string json = BuildPositionJson(ticket, symbol);
      string response;
      HttpPost(InpApiBaseUrl + "/position-analysis", json, response);
      Print("Position analysis sent for ticket ", ticket, ": ", response);
   }
}

//+------------------------------------------------------------------+
int ParseSymbols(string list, string &out[])
{
   string parts[];
   int n = StringSplit(list, ',', parts);
   int c = 0;
   ArrayResize(out, n);
   for(int i = 0; i < n; i++)
   {
      string s = parts[i];
      StringTrimLeft(s);
      StringTrimRight(s);
      if(s == "") continue;
      out[c++] = s;
   }
   ArrayResize(out, c);
   return c;
}

//+------------------------------------------------------------------+
string JsonGetSymbolsCsv(string json)
{
   int start = StringFind(json, "\"symbols\":[");
   if(start < 0)
      return "";

   start = StringFind(json, "[", start);
   int end = StringFind(json, "]", start);
   if(start < 0 || end < 0 || end <= start)
      return "";

   string inner = StringSubstr(json, start + 1, end - start - 1);
   string result = "";
   string parts[];
   int count = StringSplit(inner, ',', parts);

   for(int i = 0; i < count; i++)
   {
      string sym = parts[i];
      StringReplace(sym, "\"", "");
      StringTrimLeft(sym);
      StringTrimRight(sym);
      if(sym == "")
         continue;
      if(result != "")
         result += ",";
      result += sym;
   }
   return result;
}

//+------------------------------------------------------------------+
bool JsonGetBool(string json, string key, bool defaultValue)
{
   string search = "\"" + key + "\":";
   int pos = StringFind(json, search);
   if(pos < 0)
      return defaultValue;

   string tail = StringSubstr(json, pos + StringLen(search), 8);
   if(StringFind(tail, "true") == 0)
      return true;
   if(StringFind(tail, "false") == 0)
      return false;
   return defaultValue;
}

//+------------------------------------------------------------------+
string BuildSymbolJson(string symbol, string timeframe)
{
   ENUM_TIMEFRAMES tf = InpTimeframe;
   int ema20H = iMA(symbol, tf, 20, 0, MODE_EMA, PRICE_CLOSE);
   int ema50H = iMA(symbol, tf, 50, 0, MODE_EMA, PRICE_CLOSE);
   int ema200H = iMA(symbol, tf, 200, 0, MODE_EMA, PRICE_CLOSE);
   int rsiH = iRSI(symbol, tf, 14, PRICE_CLOSE);
   int atrH = iATR(symbol, tf, 14);
   double ema20[1], ema50[1], ema200[1], rsi[1], atr[1];
   CopyBuffer(ema20H, 0, 1, 1, ema20);
   CopyBuffer(ema50H, 0, 1, 1, ema50);
   CopyBuffer(ema200H, 0, 1, 1, ema200);
   CopyBuffer(rsiH, 0, 1, 1, rsi);
   CopyBuffer(atrH, 0, 1, 1, atr);
   IndicatorRelease(ema20H); IndicatorRelease(ema50H); IndicatorRelease(ema200H);
   IndicatorRelease(rsiH); IndicatorRelease(atrH);

   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   MqlRates rates[];
   int copied = CopyRates(symbol, tf, 1, InpCandleCount, rates);

   string json = "{\"symbol\":\"" + symbol + "\",\"timeframe\":\"" + timeframe + "\",";
   json += "\"indicators\":{\"ema20\":" + DoubleToString(ema20[0], digits) + ",";
   json += "\"ema50\":" + DoubleToString(ema50[0], digits) + ",";
   json += "\"ema200\":" + DoubleToString(ema200[0], digits) + ",";
   json += "\"rsi\":" + DoubleToString(rsi[0], 2) + ",";
   json += "\"atr\":" + DoubleToString(atr[0], digits) + "},";
   json += "\"candles\":[";
   for(int i = copied - 1; i >= 0; i--)
   {
      if(i < copied - 1) json += ",";
      json += "{\"time\":\"" + TimeToString(rates[i].time, TIME_DATE|TIME_MINUTES) + "\",";
      json += "\"open\":" + DoubleToString(rates[i].open, digits) + ",";
      json += "\"high\":" + DoubleToString(rates[i].high, digits) + ",";
      json += "\"low\":" + DoubleToString(rates[i].low, digits) + ",";
      json += "\"close\":" + DoubleToString(rates[i].close, digits) + ",";
      json += "\"volume\":" + IntegerToString((int)rates[i].tick_volume) + "}";
   }
   json += "]}";
   return json;
}

//+------------------------------------------------------------------+
string BuildPositionJson(ulong ticket, string symbol)
{
   double entry = PositionGetDouble(POSITION_PRICE_OPEN);
   double sl = PositionGetDouble(POSITION_SL);
   double tp = PositionGetDouble(POSITION_TP);
   double profit = PositionGetDouble(POSITION_PROFIT);
   long type = PositionGetInteger(POSITION_TYPE);
   datetime openTime = (datetime)PositionGetInteger(POSITION_TIME);
   int durationMinutes = (int)((TimeCurrent() - openTime) / 60);
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   double currentPrice = (type == POSITION_TYPE_BUY) ? SymbolInfoDouble(symbol, SYMBOL_BID) : SymbolInfoDouble(symbol, SYMBOL_ASK);

   string json = "{\"ticket\":" + IntegerToString((long)ticket) + ",";
   json += "\"position\":{\"symbol\":\"" + symbol + "\",";
   json += "\"type\":\"" + (type == POSITION_TYPE_BUY ? "BUY" : "SELL") + "\",";
   json += "\"entry_price\":" + DoubleToString(entry, digits) + ",";
   json += "\"current_price\":" + DoubleToString(currentPrice, digits) + ",";
   json += "\"profit\":" + DoubleToString(profit, 2) + ",";
   json += "\"sl\":" + DoubleToString(sl, digits) + ",";
   json += "\"tp\":" + DoubleToString(tp, digits) + ",";
   json += "\"duration_minutes\":" + IntegerToString(durationMinutes) + "},";
   json += "\"market_data\":{\"candles\":[],\"indicators\":{}}}";
   return json;
}

//+------------------------------------------------------------------+
bool HttpGet(string url, string &response)
{
   char data[];
   char result[];
   string headers = "X-API-TOKEN: " + InpApiToken + "\r\n";
   int res = WebRequest("GET", url, headers, 10000, data, result, headers);
   if(res == -1)
   {
      Print("WebRequest GET failed. Error: ", GetLastError());
      return false;
   }
   response = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   return res == 200;
}

//+------------------------------------------------------------------+
bool HttpPost(string url, string body, string &response)
{
   char data[];
   char result[];
   string headers = "Content-Type: application/json\r\nX-API-TOKEN: " + InpApiToken + "\r\n";
   StringToCharArray(body, data, 0, WHOLE_ARRAY, CP_UTF8);
   ArrayResize(data, StringLen(body));
   int res = WebRequest("POST", url, headers, 10000, data, result, headers);
   if(res == -1)
   {
      Print("WebRequest failed. Error: ", GetLastError());
      return false;
   }
   response = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   return res == 200 || res == 201 || res == 202;
}

//+------------------------------------------------------------------+
string TimeframeToString(ENUM_TIMEFRAMES tf)
{
   switch(tf)
   {
      case PERIOD_M1:  return "M1";
      case PERIOD_M5:  return "M5";
      case PERIOD_M15: return "M15";
      case PERIOD_M30: return "M30";
      case PERIOD_H1:  return "H1";
      case PERIOD_H4:  return "H4";
      case PERIOD_D1:  return "D1";
      default:         return "M15";
   }
}

//+------------------------------------------------------------------+
