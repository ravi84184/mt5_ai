//+------------------------------------------------------------------+
//| AI_Trading_EA.mq5                                                |
//| AI Trading Platform - MetaTrader 5 Expert Advisor                |
//+------------------------------------------------------------------+
#property copyright "MT5 AI Trading Platform"
#property version   "2.00"
#property strict

#include <Trade/Trade.mqh>

//--- Inputs
input string   InpApiBaseUrl      = "https://mt5-ai.niksofts.com/api";
input string   InpApiToken        = "";  // Per-account token from Super Admin
input int      InpPollIntervalSec = 7;
input int      InpConfigPollSec   = 210; // Re-fetch admin settings (0 = use timer cycles)
input int      InpCandleCount     = 50;
input double   InpMinConfidence   = 80.0;  // Fallback if admin has no override
input double   InpRiskPerTradePct = 1.0;
input int      InpMaxOpenTrades   = 3;   // Fallback if admin has no override
input string   InpSymbols         = "XAUUSD";  // Fallback only when InpAllowSymbolFallback=true
input ENUM_TIMEFRAMES InpTimeframe = PERIOD_M15;
input int      InpMagicNumber     = 20260625;
input bool     InpShowButtons     = true;
input bool     InpUseServerConfig = true;   // Symbols & limits from Super Admin
input bool     InpAllowSymbolFallback = false; // Use InpSymbols when admin has none set

//--- Globals
#define BTN_ASK_AI    "AiBtn_AskEntry"
#define BTN_MANAGE    "AiBtn_ManagePos"

CTrade         trade;
datetime       g_lastBarTime = 0;
string         g_symbols[];
int            g_symbolCount = 0;
bool           g_tradingEnabled = true;
bool           g_configured = false;
int            g_minConfidence = 80;
int            g_maxOpenTrades = 3;
string         g_aiProvider = "";
int            g_configPollCounter = 0;
int            g_configPollEvery = 30;

//+------------------------------------------------------------------+
int OnInit()
{
   if(StringLen(InpApiToken) < 8)
   {
      Print("ERROR: Set InpApiToken — generate in Super Admin → Accounts → Generate API token");
      return INIT_PARAMETERS_INCORRECT;
   }

   trade.SetExpertMagicNumber(InpMagicNumber);
   trade.SetDeviationInPoints(20);
   trade.SetTypeFilling(ORDER_FILLING_IOC);

   g_minConfidence = (int)InpMinConfidence;
   g_maxOpenTrades = InpMaxOpenTrades;
   g_configPollEvery = (InpConfigPollSec > 0) ? MathMax(1, InpConfigPollSec / InpPollIntervalSec) : 30;

   if(InpUseServerConfig)
   {
      if(!FetchAccountConfig())
         Print("Warning: Could not load admin config — check API token and WebRequest URL");
   }
   else
   {
      if(!LoadSymbolList(InpSymbols))
      {
         Print("Failed to parse InpSymbols: ", InpSymbols);
         return INIT_FAILED;
      }
   }

   if(g_symbolCount <= 0)
   {
      Print("No symbols to trade. Configure symbols in Super Admin: /admin/accounts");
      if(!InpAllowSymbolFallback)
         return INIT_FAILED;
      if(!LoadSymbolList(InpSymbols))
         return INIT_FAILED;
   }

   if(!EnsureSymbolsInMarketWatch())
      Print("Warning: Some symbols are missing from Market Watch");

   EventSetTimer(InpPollIntervalSec);
   UpdateChartComment();
   Print("AI Trading EA v2 started | symbols=", g_symbolCount,
         " | trading=", g_tradingEnabled ? "ON" : "OFF",
         " | AI=", g_aiProvider);
   Print("API: ", InpApiBaseUrl, " | Account: ", AccountInfoInteger(ACCOUNT_LOGIN));

   if(g_tradingEnabled && g_symbolCount > 0)
      SendMarketData();

   if(InpShowButtons)
      CreateManualButtons();

   return INIT_SUCCEEDED;
}

//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   EventKillTimer();
   DeleteManualButtons();
   Comment("");
}

//+------------------------------------------------------------------+
void OnChartEvent(const int id, const long &lparam, const double &dparam, const string &sparam)
{
   if(id != CHARTEVENT_OBJECT_CLICK)
      return;

   if(sparam == BTN_ASK_AI)
   {
      ObjectSetInteger(0, BTN_ASK_AI, OBJPROP_STATE, false);
      ChartRedraw();
      Print("=== Manual AI entry analysis requested ===");
      if(g_symbolCount <= 0)
      {
         Print("No symbols configured in Super Admin (/admin/accounts)");
         return;
      }
      SendMarketData();
   }
   else if(sparam == BTN_MANAGE)
   {
      ObjectSetInteger(0, BTN_MANAGE, OBJPROP_STATE, false);
      ChartRedraw();
      Print("=== Manual AI position management requested ===");
      SendOpenPositionsAnalysis();
   }
}

//+------------------------------------------------------------------+
void CreateManualButtons()
{
   CreateChartButton(BTN_ASK_AI, "Ask AI Entry", 10, 30, 120, 28);
   CreateChartButton(BTN_MANAGE, "Manage Open", 140, 30, 120, 28);
   ChartRedraw();
}

//+------------------------------------------------------------------+
void DeleteManualButtons()
{
   ObjectDelete(0, BTN_ASK_AI);
   ObjectDelete(0, BTN_MANAGE);
   ChartRedraw();
}

//+------------------------------------------------------------------+
void CreateChartButton(string name, string text, int x, int y, int w, int h)
{
   if(ObjectFind(0, name) >= 0)
      ObjectDelete(0, name);

   ObjectCreate(0, name, OBJ_BUTTON, 0, 0, 0);
   ObjectSetInteger(0, name, OBJPROP_XDISTANCE, x);
   ObjectSetInteger(0, name, OBJPROP_YDISTANCE, y);
   ObjectSetInteger(0, name, OBJPROP_XSIZE, w);
   ObjectSetInteger(0, name, OBJPROP_YSIZE, h);
   ObjectSetString(0, name, OBJPROP_TEXT, text);
   ObjectSetInteger(0, name, OBJPROP_CORNER, CORNER_LEFT_UPPER);
   ObjectSetInteger(0, name, OBJPROP_FONTSIZE, 9);
   ObjectSetInteger(0, name, OBJPROP_COLOR, clrWhite);
   ObjectSetInteger(0, name, OBJPROP_BGCOLOR, clrDodgerBlue);
   ObjectSetInteger(0, name, OBJPROP_BORDER_COLOR, clrNavy);
   ObjectSetInteger(0, name, OBJPROP_SELECTABLE, false);
   ObjectSetInteger(0, name, OBJPROP_HIDDEN, true);
}

//+------------------------------------------------------------------+
void OnTimer()
{
   g_configPollCounter++;
   if(InpUseServerConfig && g_configPollCounter >= g_configPollEvery)
   {
      g_configPollCounter = 0;
      if(FetchAccountConfig())
         UpdateChartComment();
   }

   if(g_tradingEnabled)
      PollSignals();

   PollManagementActions();
}

//+------------------------------------------------------------------+
void OnTick()
{
   datetime barTime = iTime(_Symbol, InpTimeframe, 0);
   if(barTime == g_lastBarTime)
      return;

   g_lastBarTime = barTime;

   if(g_tradingEnabled && g_symbolCount > 0)
      SendMarketData();

   SendOpenPositionsAnalysis();
}

//+------------------------------------------------------------------+
void OnTradeTransaction(const MqlTradeTransaction &trans,
                        const MqlTradeRequest &request,
                        const MqlTradeResult &result)
{
   if(trans.type == TRADE_TRANSACTION_DEAL_ADD)
   {
      SendTradeUpdate(trans);
   }
}

//+------------------------------------------------------------------+
bool LoadSymbolList(string csv)
{
   string source = csv;
   if(source == "" && g_symbolCount > 0)
      return true;

   if(source == "")
      source = InpSymbols;

   string parts[];
   int count = StringSplit(source, ',', parts);
   if(count <= 0)
      return false;

   ArrayResize(g_symbols, count);
   g_symbolCount = 0;

   for(int i = 0; i < count; i++)
   {
      string sym = Trim(parts[i]);
      if(sym == "")
         continue;
      g_symbols[g_symbolCount] = sym;
      g_symbolCount++;
   }

   ArrayResize(g_symbols, g_symbolCount);
   return g_symbolCount > 0;
}

//+------------------------------------------------------------------+
bool ParseSymbols()
{
   return LoadSymbolList(InpSymbols);
}

//+------------------------------------------------------------------+
bool EnsureSymbolsInMarketWatch()
{
   bool allOk = true;
   for(int i = 0; i < g_symbolCount; i++)
   {
      if(!SymbolInfoInteger(g_symbols[i], SYMBOL_EXIST))
      {
         Print("Symbol not found on broker: ", g_symbols[i]);
         allOk = false;
         continue;
      }
      if(!SymbolSelect(g_symbols[i], true))
      {
         Print("Failed to add to Market Watch: ", g_symbols[i]);
         allOk = false;
      }
   }
   return allOk;
}

//+------------------------------------------------------------------+
void UpdateChartComment()
{
   string lines = "MT5 AI EA v2\n";
   lines += "Account: " + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN)) + "\n";
   lines += "Trading: " + (g_tradingEnabled ? "ON" : "OFF") + "\n";
   lines += "AI: " + (g_aiProvider != "" ? g_aiProvider : "default") + "\n";
   lines += "Symbols: " + IntegerToString(g_symbolCount) + "\n";
   lines += "Min conf: " + IntegerToString(g_minConfidence) + "%\n";
   lines += "Max trades: " + IntegerToString(g_maxOpenTrades);
   Comment(lines);
}

//+------------------------------------------------------------------+
bool FetchAccountConfig()
{
   string url = InpApiBaseUrl + "/account-config?account=" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN));
   string response;
   if(!HttpGet(url, response))
   {
      Print("Account config fetch failed — check WebRequest URL and API token");
      return false;
   }

   g_tradingEnabled = JsonGetBool(response, "trading_enabled", false);
   g_configured = JsonGetBool(response, "configured", false);
   g_aiProvider = JsonGetString(response, "ai_provider");

   int serverMinConf = JsonGetInt(response, "min_confidence");
   if(serverMinConf > 0)
      g_minConfidence = serverMinConf;

   int serverMaxTrades = JsonGetInt(response, "max_open_trades");
   if(serverMaxTrades > 0)
      g_maxOpenTrades = serverMaxTrades;

   string symbolsCsv = JsonGetSymbolsCsv(response);
   if(symbolsCsv != "")
   {
      if(!LoadSymbolList(symbolsCsv))
         Print("Server symbols parse failed: ", symbolsCsv);
   }
   else if(g_configured == false && InpAllowSymbolFallback)
   {
      LoadSymbolList(InpSymbols);
   }
   else if(g_configured == false)
   {
      g_symbolCount = 0;
      ArrayResize(g_symbols, 0);
   }

   Print("Admin config: trading=", g_tradingEnabled ? "ON" : "OFF",
         " | configured=", g_configured ? "yes" : "no",
         " | AI=", g_aiProvider,
         " | symbols=", g_symbolCount,
         " | minConf=", g_minConfidence,
         " | maxTrades=", g_maxOpenTrades);

   return true;
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
      string sym = Trim(parts[i]);
      StringReplace(sym, "\"", "");
      if(sym == "")
         continue;
      if(result != "")
         result += ",";
      result += sym;
   }

   return result;
}

//+------------------------------------------------------------------+
bool JsonGetBool(string json, string key, bool defaultValue = false)
{
   string searchTrue = "\"" + key + "\":true";
   string searchFalse = "\"" + key + "\":false";
   if(StringFind(json, searchTrue) >= 0)
      return true;
   if(StringFind(json, searchFalse) >= 0)
      return false;
   return defaultValue;
}

//+------------------------------------------------------------------+
string Trim(string value)
{
   StringTrimLeft(value);
   StringTrimRight(value);
   return value;
}

//+------------------------------------------------------------------+
void SendMarketData()
{
   if(g_symbolCount <= 0)
   {
      Print("Market data skipped — no symbols configured in Super Admin");
      return;
   }

   string json = BuildMarketDataJson();
   string response;
   if(HttpPost(InpApiBaseUrl + "/market-data", json, response))
      Print("Market data sent: ", response);
   else
      Print("Market data FAILED. Response: ", response);
}

//+------------------------------------------------------------------+
string BuildMarketDataJson()
{
   string tf = TimeframeToString(InpTimeframe);
   string json = "{";
   json += "\"account\":{";
   json += "\"login\":" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN)) + ",";
   json += "\"balance\":" + DoubleToString(AccountInfoDouble(ACCOUNT_BALANCE), 2) + ",";
   json += "\"equity\":" + DoubleToString(AccountInfoDouble(ACCOUNT_EQUITY), 2) + ",";
   json += "\"free_margin\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN_FREE), 2);
   json += "},";
   json += "\"symbols\":[";

   bool first = true;
   for(int i = 0; i < g_symbolCount; i++)
   {
      if(!SymbolInfoInteger(g_symbols[i], SYMBOL_EXIST))
      {
         Print("Skipping missing symbol: ", g_symbols[i]);
         continue;
      }
      if(!first) json += ",";
      first = false;
      json += BuildSymbolJson(g_symbols[i], tf);
   }

   json += "]}";
   return json;
}

//+------------------------------------------------------------------+
string BuildSymbolJson(string symbol, string timeframe)
{
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   double bid = SymbolInfoDouble(symbol, SYMBOL_BID);
   double ask = SymbolInfoDouble(symbol, SYMBOL_ASK);
   double spread = (double)SymbolInfoInteger(symbol, SYMBOL_SPREAD);

   string json = "{";
   json += "\"symbol\":\"" + symbol + "\",";
   json += "\"timeframe\":\"" + timeframe + "\",";
   json += "\"market\":{";
   json += "\"bid\":" + DoubleToString(bid, digits) + ",";
   json += "\"ask\":" + DoubleToString(ask, digits) + ",";
   json += "\"spread\":" + DoubleToString(spread, digits) + ",";
   json += "\"digits\":" + IntegerToString(digits);
   json += "},";
   json += "\"symbol_info\":" + BuildSymbolInfoJson(symbol) + ",";
   json += "\"session\":" + BuildSessionJson() + ",";
   json += "\"levels\":" + BuildDailyLevelsJson(symbol) + ",";
   json += "\"indicators\":" + BuildIndicatorsJson(symbol, InpTimeframe) + ",";
   json += "\"multi_timeframe\":{";
   json += "\"H1\":{\"indicators\":" + BuildIndicatorsJson(symbol, PERIOD_H1) + "},";
   json += "\"H4\":{\"indicators\":" + BuildIndicatorsJson(symbol, PERIOD_H4) + "}";
   json += "},";
   json += "\"correlation\":" + BuildCorrelationJson(symbol) + ",";
   json += "\"candles\":" + BuildCandlesJson(symbol, InpTimeframe);
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildSymbolInfoJson(string symbol)
{
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   double point = SymbolInfoDouble(symbol, SYMBOL_POINT);
   int stopsLevel = (int)SymbolInfoInteger(symbol, SYMBOL_TRADE_STOPS_LEVEL);
   string json = "{";
   json += "\"digits\":" + IntegerToString(digits) + ",";
   json += "\"point\":" + DoubleToString(point, digits) + ",";
   json += "\"min_stop_distance_points\":" + IntegerToString(stopsLevel) + ",";
   json += "\"min_stop_distance\":" + DoubleToString(stopsLevel * point, digits) + ",";
   json += "\"typical_spread_points\":" + IntegerToString((int)SymbolInfoInteger(symbol, SYMBOL_SPREAD));
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildSessionJson()
{
   datetime gmt = TimeGMT();
   MqlDateTime dt;
   TimeToStruct(gmt, dt);
   string session = "asia";
   if(dt.hour >= 7 && dt.hour < 12) session = "london";
   else if(dt.hour >= 12 && dt.hour < 17) session = "london_ny_overlap";
   else if(dt.hour >= 17 && dt.hour < 22) session = "new_york";
   string days[] = {"sunday","monday","tuesday","wednesday","thursday","friday","saturday"};
   string json = "{";
   json += "\"utc_hour\":" + IntegerToString(dt.hour) + ",";
   json += "\"day_of_week\":\"" + days[dt.day_of_week] + "\",";
   json += "\"session\":\"" + session + "\"";
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildDailyLevelsJson(string symbol)
{
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   MqlRates d1[], w1[];
   double pdh = 0, pdl = 0, pdc = 0, weekHigh = 0, weekLow = 0;
   if(CopyRates(symbol, PERIOD_D1, 1, 1, d1) == 1) { pdh = d1[0].high; pdl = d1[0].low; pdc = d1[0].close; }
   if(CopyRates(symbol, PERIOD_W1, 0, 1, w1) == 1) { weekHigh = w1[0].high; weekLow = w1[0].low; }
   string json = "{";
   json += "\"prev_day_high\":" + DoubleToString(pdh, digits) + ",";
   json += "\"prev_day_low\":" + DoubleToString(pdl, digits) + ",";
   json += "\"prev_day_close\":" + DoubleToString(pdc, digits) + ",";
   json += "\"week_high\":" + DoubleToString(weekHigh, digits) + ",";
   json += "\"week_low\":" + DoubleToString(weekLow, digits);
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildCorrelationJson(string symbol)
{
   string upper = symbol;
   StringToUpper(upper);
   if(StringFind(upper, "XAU") < 0 && StringFind(upper, "PAXG") < 0) return "{}";
   string dxySymbols[] = {"USDX", "DXY", "DX.f", "USDIndex"};
   string dxySymbol = "";
   for(int i = 0; i < ArraySize(dxySymbols); i++)
      if(SymbolSelect(dxySymbols[i], true)) { dxySymbol = dxySymbols[i]; break; }
   if(dxySymbol == "") return "{}";
   int digits = (int)SymbolInfoInteger(dxySymbol, SYMBOL_DIGITS);
   int ema20H = iMA(dxySymbol, PERIOD_H1, 20, 0, MODE_EMA, PRICE_CLOSE);
   int ema50H = iMA(dxySymbol, PERIOD_H1, 50, 0, MODE_EMA, PRICE_CLOSE);
   double ema20[1], ema50[1];
   CopyBuffer(ema20H, 0, 1, 1, ema20);
   CopyBuffer(ema50H, 0, 1, 1, ema50);
   IndicatorRelease(ema20H); IndicatorRelease(ema50H);
   double close = iClose(dxySymbol, PERIOD_H1, 0);
   string trend = "neutral";
   if(close > ema20[0] && ema20[0] > ema50[0]) trend = "bullish";
   else if(close < ema20[0] && ema20[0] < ema50[0]) trend = "bearish";
   string json = "{";
   json += "\"dxy_symbol\":\"" + dxySymbol + "\",";
   json += "\"dxy_trend\":\"" + trend + "\"";
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildIndicatorsJson(string symbol, ENUM_TIMEFRAMES tf)
{
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   int ema20H = iMA(symbol, tf, 20, 0, MODE_EMA, PRICE_CLOSE);
   int ema50H = iMA(symbol, tf, 50, 0, MODE_EMA, PRICE_CLOSE);
   int ema200H = iMA(symbol, tf, 200, 0, MODE_EMA, PRICE_CLOSE);
   int rsiH = iRSI(symbol, tf, 14, PRICE_CLOSE);
   int atrH = iATR(symbol, tf, 14);
   int macdH = iMACD(symbol, tf, 12, 26, 9, PRICE_CLOSE);
   int adxH = iADX(symbol, tf, 14);
   double ema20[1], ema50[1], ema200[1], rsi[1], atr[1], macdHist[1], adx[1];
   CopyBuffer(ema20H, 0, 1, 1, ema20);
   CopyBuffer(ema50H, 0, 1, 1, ema50);
   CopyBuffer(ema200H, 0, 1, 1, ema200);
   CopyBuffer(rsiH, 0, 1, 1, rsi);
   CopyBuffer(atrH, 0, 1, 1, atr);
   CopyBuffer(macdH, 2, 1, 1, macdHist);
   CopyBuffer(adxH, 0, 1, 1, adx);
   IndicatorRelease(ema20H); IndicatorRelease(ema50H); IndicatorRelease(ema200H);
   IndicatorRelease(rsiH); IndicatorRelease(atrH); IndicatorRelease(macdH); IndicatorRelease(adxH);
   string json = "{";
   json += "\"ema20\":" + DoubleToString(ema20[0], digits) + ",";
   json += "\"ema50\":" + DoubleToString(ema50[0], digits) + ",";
   json += "\"ema200\":" + DoubleToString(ema200[0], digits) + ",";
   json += "\"rsi\":" + DoubleToString(rsi[0], 2) + ",";
   json += "\"atr\":" + DoubleToString(atr[0], digits) + ",";
   json += "\"macd_histogram\":" + DoubleToString(macdHist[0], digits) + ",";
   json += "\"adx\":" + DoubleToString(adx[0], 2);
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildCandlesJson(string symbol, ENUM_TIMEFRAMES tf)
{
   MqlRates rates[];
   int copied = CopyRates(symbol, tf, 1, InpCandleCount, rates);
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   string json = "[";
   for(int i = copied - 1; i >= 0; i--)
   {
      if(i < copied - 1) json += ",";
      json += "{";
      json += "\"time\":\"" + TimeToString(rates[i].time, TIME_DATE|TIME_MINUTES) + "\",";
      json += "\"open\":" + DoubleToString(rates[i].open, digits) + ",";
      json += "\"high\":" + DoubleToString(rates[i].high, digits) + ",";
      json += "\"low\":" + DoubleToString(rates[i].low, digits) + ",";
      json += "\"close\":" + DoubleToString(rates[i].close, digits) + ",";
      json += "\"volume\":" + IntegerToString((int)rates[i].tick_volume);
      json += "}";
   }
   json += "]";
   return json;
}

//+------------------------------------------------------------------+
void PollSignals()
{
   if(!TerminalInfoInteger(TERMINAL_TRADE_ALLOWED) || !MQLInfoInteger(MQL_TRADE_ALLOWED))
   {
      Print("Signal poll skipped — AutoTrading disabled (enable Algo Trading in MT5)");
      return;
   }

   string url = InpApiBaseUrl + "/signals?account=" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN));
   string response;
   if(!HttpGet(url, response))
      return;

   if(StringFind(response, "NO_SIGNAL") >= 0)
      return;

   int signalId = (int)JsonGetInt(response, "id");
   string symbol = JsonGetString(response, "symbol");
   string action = JsonGetString(response, "action");
   double confidence = JsonGetDouble(response, "confidence");
   double entry = JsonGetDouble(response, "entry_price");
   double sl = JsonGetDouble(response, "stop_loss");
   double tp = JsonGetDouble(response, "take_profit");

   StringToUpper(action);

   if(signalId <= 0 || symbol == "" || (action != "BUY" && action != "SELL"))
   {
      Print("Signal parse failed: ", response);
      return;
   }

   Print("Pending signal #", signalId, ": ", action, " ", symbol,
         " conf=", confidence, " entry=", entry, " sl=", sl, " tp=", tp);

   if(!SymbolSelect(symbol, true))
   {
      Print("Signal rejected: symbol not available on broker — ", symbol);
      NotifySignalFailed(signalId, "Symbol not available on broker: " + symbol);
      return;
   }

   if(CountOpenTrades() >= g_maxOpenTrades)
   {
      Print("Signal rejected: max open trades reached (", g_maxOpenTrades, ")");
      NotifySignalFailed(signalId, "Max open trades reached on MT5");
      return;
   }

   if(HasOpenPosition(symbol))
   {
      Print("Signal rejected: position already open on ", symbol);
      NotifySignalFailed(signalId, "Position already open on " + symbol);
      return;
   }

   double marketPrice = (action == "BUY")
      ? SymbolInfoDouble(symbol, SYMBOL_ASK)
      : SymbolInfoDouble(symbol, SYMBOL_BID);

   if(entry <= 0)
      entry = marketPrice;

   if(sl <= 0 || tp <= 0)
   {
      Print("Signal rejected: missing stop_loss or take_profit");
      NotifySignalFailed(signalId, "Missing stop_loss or take_profit");
      return;
   }

   if(!NormalizeStopsForBroker(symbol, action, sl, tp, marketPrice))
   {
      Print("Signal rejected: invalid SL/TP for broker stops level");
      NotifySignalFailed(signalId, "Invalid SL/TP for broker minimum stop distance");
      return;
   }

   double lot = CalculateLotSize(symbol, entry, sl);
   if(lot <= 0)
   {
      Print("Signal rejected: lot size is zero");
      NotifySignalFailed(signalId, "Calculated lot size is zero");
      return;
   }

   bool success = ExecuteSignalTrade(symbol, action, lot, sl, tp, signalId);
   if(success)
   {
      ulong ticket = trade.ResultDeal();
      if(ticket == 0)
         ticket = trade.ResultOrder();
      double fillPrice = trade.ResultPrice();
      if(fillPrice <= 0)
         fillPrice = marketPrice;
      Print("Trade executed: ", action, " ", symbol, " lot=", lot, " ticket=", ticket, " price=", fillPrice);
      NotifySignalExecuted(signalId, ticket, symbol, action, lot, fillPrice);
   }
   else
   {
      string err = "Trade failed retcode=" + IntegerToString((int)trade.ResultRetcode())
         + " err=" + IntegerToString(GetLastError())
         + " comment=" + trade.ResultComment();
      Print("Trade FAILED: ", action, " ", symbol, " — ", err);
   }
}

//+------------------------------------------------------------------+
bool ExecuteSignalTrade(string symbol, string action, double lot, double sl, double tp, int signalId)
{
   string comment = "AI Signal #" + IntegerToString(signalId);
   ENUM_ORDER_TYPE_FILLING fillings[3] = {ORDER_FILLING_IOC, ORDER_FILLING_FOK, ORDER_FILLING_RETURN};

   for(int i = 0; i < 3; i++)
   {
      trade.SetTypeFilling(fillings[i]);
      bool success = false;
      if(action == "BUY")
         success = trade.Buy(lot, symbol, 0, sl, tp, comment);
      else
         success = trade.Sell(lot, symbol, 0, sl, tp, comment);

      if(success)
         return true;

      uint retcode = trade.ResultRetcode();
      if(retcode != TRADE_RETCODE_INVALID_FILL && retcode != TRADE_RETCODE_INVALID_ORDER)
         break;
   }

   return false;
}

//+------------------------------------------------------------------+
bool NormalizeStopsForBroker(string symbol, string action, double &sl, double &tp, double marketPrice)
{
   double point = SymbolInfoDouble(symbol, SYMBOL_POINT);
   if(point <= 0)
      return false;

   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   int stopsLevel = (int)SymbolInfoInteger(symbol, SYMBOL_TRADE_STOPS_LEVEL);
   double minDist = stopsLevel * point;

   sl = NormalizeDouble(sl, digits);
   tp = NormalizeDouble(tp, digits);
   marketPrice = NormalizeDouble(marketPrice, digits);

   if(action == "BUY")
   {
      if(sl >= marketPrice || tp <= marketPrice)
         return false;
      if(minDist > 0)
      {
         if((marketPrice - sl) < minDist)
            sl = NormalizeDouble(marketPrice - minDist, digits);
         if((tp - marketPrice) < minDist)
            tp = NormalizeDouble(marketPrice + minDist, digits);
      }
   }
   else
   {
      if(sl <= marketPrice || tp >= marketPrice)
         return false;
      if(minDist > 0)
      {
         if((sl - marketPrice) < minDist)
            sl = NormalizeDouble(marketPrice + minDist, digits);
         if((marketPrice - tp) < minDist)
            tp = NormalizeDouble(marketPrice - minDist, digits);
      }
   }

   return true;
}

//+------------------------------------------------------------------+
void NotifySignalFailed(int signalId, string reason)
{
   string safeReason = reason;
   StringReplace(safeReason, "\\", " ");
   StringReplace(safeReason, "\"", "'");

   string json = "{";
   json += "\"signal_id\":" + IntegerToString(signalId) + ",";
   json += "\"reason\":\"" + safeReason + "\"";
   json += "}";

   string response;
   HttpPost(InpApiBaseUrl + "/signals/failed", json, response);
}

//+------------------------------------------------------------------+
void NotifySignalExecuted(int signalId, ulong ticket, string symbol, string action, double lot, double entry)
{
   string json = "{";
   json += "\"signal_id\":" + IntegerToString(signalId) + ",";
   json += "\"ticket\":" + IntegerToString((long)ticket) + ",";
   json += "\"status\":\"EXECUTED\",";
   json += "\"symbol\":\"" + symbol + "\",";
   json += "\"type\":\"" + action + "\",";
   json += "\"lot\":" + DoubleToString(lot, 2) + ",";
   json += "\"entry_price\":" + DoubleToString(entry, _Digits);
   json += "}";

   string response;
   HttpPost(InpApiBaseUrl + "/signals/executed", json, response);
}

//+------------------------------------------------------------------+
void SendOpenPositionsAnalysis()
{
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(!PositionSelectByTicket(ticket)) continue;
      if((int)PositionGetInteger(POSITION_MAGIC) != InpMagicNumber) continue;

      string symbol = PositionGetString(POSITION_SYMBOL);
      string json = BuildPositionAnalysisJson(ticket, symbol);
      string response;
      HttpPost(InpApiBaseUrl + "/position-analysis", json, response);
   }
}

//+------------------------------------------------------------------+
string BuildPositionAnalysisJson(ulong ticket, string symbol)
{
   double entry = PositionGetDouble(POSITION_PRICE_OPEN);
   double sl = PositionGetDouble(POSITION_SL);
   double tp = PositionGetDouble(POSITION_TP);
   double profit = PositionGetDouble(POSITION_PROFIT);
   long type = PositionGetInteger(POSITION_TYPE);
   datetime openTime = (datetime)PositionGetInteger(POSITION_TIME);
   int durationMinutes = (int)((TimeCurrent() - openTime) / 60);
   double currentPrice = (type == POSITION_TYPE_BUY) ? SymbolInfoDouble(symbol, SYMBOL_BID) : SymbolInfoDouble(symbol, SYMBOL_ASK);
   string tf = TimeframeToString(InpTimeframe);

   string json = "{";
   json += "\"ticket\":" + IntegerToString((long)ticket) + ",";
   json += "\"position\":{";
   json += "\"symbol\":\"" + symbol + "\",";
   json += "\"type\":\"" + (type == POSITION_TYPE_BUY ? "BUY" : "SELL") + "\",";
   json += "\"entry_price\":" + DoubleToString(entry, _Digits) + ",";
   json += "\"current_price\":" + DoubleToString(currentPrice, _Digits) + ",";
   json += "\"profit\":" + DoubleToString(profit, 2) + ",";
   json += "\"sl\":" + DoubleToString(sl, _Digits) + ",";
   json += "\"tp\":" + DoubleToString(tp, _Digits) + ",";
   json += "\"duration_minutes\":" + IntegerToString(durationMinutes);
   json += "},";
   json += "\"market_data\":{";
   json += "\"timeframe\":\"" + tf + "\",";
   json += "\"market\":{";
   json += "\"bid\":" + DoubleToString(SymbolInfoDouble(symbol, SYMBOL_BID), _Digits) + ",";
   json += "\"ask\":" + DoubleToString(SymbolInfoDouble(symbol, SYMBOL_ASK), _Digits);
   json += "},";
   json += "\"candles\":" + BuildCandlesJson(symbol, InpTimeframe) + ",";
   json += "\"indicators\":" + BuildIndicatorsJson(symbol, InpTimeframe) + ",";
   json += "\"levels\":" + BuildDailyLevelsJson(symbol) + ",";
   json += "\"multi_timeframe\":{";
   json += "\"H1\":{\"indicators\":" + BuildIndicatorsJson(symbol, PERIOD_H1) + "},";
   json += "\"H4\":{\"indicators\":" + BuildIndicatorsJson(symbol, PERIOD_H4) + "}";
   json += "}}}";
   return json;
}

//+------------------------------------------------------------------+
void PollManagementActions()
{
   string url = InpApiBaseUrl + "/signals/management?account=" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN));
   string response;
   if(!HttpGet(url, response))
      return;

   if(StringFind(response, "NO_ACTION") >= 0)
      return;

   ulong ticket = (ulong)JsonGetInt(response, "ticket");
   string action = JsonGetString(response, "action");
   double newSl = JsonGetDouble(response, "new_sl");
   double closeVolume = JsonGetDouble(response, "close_volume");

   if(!PositionSelectByTicket(ticket))
      return;

   bool applied = false;
   string symbol = PositionGetString(POSITION_SYMBOL);

   if(action == "CLOSE")
      applied = trade.PositionClose(ticket);
   else if(action == "MOVE_SL" || action == "MOVE_TO_BREAKEVEN")
      applied = trade.PositionModify(ticket, newSl, PositionGetDouble(POSITION_TP));
   else if(action == "PARTIAL_CLOSE" && closeVolume > 0)
      applied = trade.PositionClosePartial(ticket, closeVolume);

   if(applied)
   {
      string json = "{";
      json += "\"ticket\":" + IntegerToString((long)ticket) + ",";
      json += "\"action\":\"" + action + "\",";
      json += "\"status\":\"APPLIED\"";
      json += "}";
      string applyResponse;
      HttpPost(InpApiBaseUrl + "/signals/management/applied", json, applyResponse);
   }
}

//+------------------------------------------------------------------+
void SendTradeUpdate(const MqlTradeTransaction &trans)
{
   if(!HistoryDealSelect(trans.deal))
      return;

   ulong ticket = (ulong)HistoryDealGetInteger(trans.deal, DEAL_POSITION_ID);
   string status = "OPEN";
   double profit = HistoryDealGetDouble(trans.deal, DEAL_PROFIT);
   double closePrice = HistoryDealGetDouble(trans.deal, DEAL_PRICE);
   long entry = HistoryDealGetInteger(trans.deal, DEAL_ENTRY);

   if(entry == DEAL_ENTRY_OUT || entry == DEAL_ENTRY_OUT_BY)
      status = "CLOSED";

   string json = "{";
   json += "\"account\":" + IntegerToString((int)AccountInfoInteger(ACCOUNT_LOGIN)) + ",";
   json += "\"ticket\":" + IntegerToString((long)ticket) + ",";
   json += "\"status\":\"" + status + "\",";
   json += "\"profit\":" + DoubleToString(profit, 2) + ",";
   json += "\"close_price\":" + DoubleToString(closePrice, _Digits);
   json += "}";

   string response;
   HttpPost(InpApiBaseUrl + "/trades/update", json, response);
}

//+------------------------------------------------------------------+
int CountOpenTrades()
{
   int count = 0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(!PositionSelectByTicket(ticket)) continue;
      if((int)PositionGetInteger(POSITION_MAGIC) == InpMagicNumber)
         count++;
   }
   return count;
}

//+------------------------------------------------------------------+
bool HasOpenPosition(string symbol)
{
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(!PositionSelectByTicket(ticket)) continue;
      if((int)PositionGetInteger(POSITION_MAGIC) != InpMagicNumber) continue;
      if(PositionGetString(POSITION_SYMBOL) == symbol)
         return true;
   }
   return false;
}

//+------------------------------------------------------------------+
double CalculateLotSize(string symbol, double entry, double sl)
{
   double balance = AccountInfoDouble(ACCOUNT_BALANCE);
   double riskAmount = balance * InpRiskPerTradePct / 100.0;
   double tickValue = SymbolInfoDouble(symbol, SYMBOL_TRADE_TICK_VALUE);
   double tickSize = SymbolInfoDouble(symbol, SYMBOL_TRADE_TICK_SIZE);
   double slDistance = MathAbs(entry - sl);

   if(slDistance <= 0 || tickSize <= 0 || tickValue <= 0)
      return SymbolInfoDouble(symbol, SYMBOL_VOLUME_MIN);

   double lot = riskAmount / (slDistance / tickSize * tickValue);

   double minLot = SymbolInfoDouble(symbol, SYMBOL_VOLUME_MIN);
   double maxLot = SymbolInfoDouble(symbol, SYMBOL_VOLUME_MAX);
   double step = SymbolInfoDouble(symbol, SYMBOL_VOLUME_STEP);

   lot = MathFloor(lot / step) * step;
   lot = MathMax(minLot, MathMin(maxLot, lot));
   return lot;
}

//+------------------------------------------------------------------+
bool HttpPost(string url, string body, string &response)
{
   char data[];
   char result[];
   string headers = "Content-Type: application/json\r\nX-API-TOKEN: " + InpApiToken + "\r\n";
   StringToCharArray(body, data, 0, WHOLE_ARRAY, CP_UTF8);
   ArrayResize(data, StringLen(body));

   int res = WebRequest("POST", url, headers, 5000, data, result, headers);
   if(res == -1)
   {
      Print("WebRequest POST failed. Add URL to MT5 allowed list: ", url, " Error: ", GetLastError());
      return false;
   }

   response = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   if(res != 200 && res != 201 && res != 202)
      Print("HTTP POST ", res, " for ", url, " — ", response);
   return res == 200 || res == 201 || res == 202;
}

//+------------------------------------------------------------------+
bool HttpGet(string url, string &response)
{
   char data[];
   char result[];
   string headers = "X-API-TOKEN: " + InpApiToken + "\r\n";

   int res = WebRequest("GET", url, headers, 5000, data, result, headers);
   if(res == -1)
   {
      Print("WebRequest GET failed: ", url, " Error: ", GetLastError());
      return false;
   }

   response = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);
   if(res != 200)
      Print("HTTP GET ", res, " for ", url, " — ", response);
   return res == 200;
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
// Simple JSON helpers (minimal parser for API responses)
//+------------------------------------------------------------------+
string JsonGetString(string json, string key)
{
   string search = "\"" + key + "\":\"";
   int start = StringFind(json, search);
   if(start < 0) return "";
   start += StringLen(search);
   int end = StringFind(json, "\"", start);
   if(end < 0) return "";
   return StringSubstr(json, start, end - start);
}

//+------------------------------------------------------------------+
int JsonGetInt(string json, string key)
{
   return (int)JsonGetDouble(json, key);
}

//+------------------------------------------------------------------+
double JsonGetDouble(string json, string key)
{
   string search = "\"" + key + "\":";
   int start = StringFind(json, search);
   if(start < 0) return 0;
   start += StringLen(search);

   string num = "";
   int len = StringLen(json);
   for(int i = start; i < len; i++)
   {
      ushort ch = StringGetCharacter(json, i);
      if((ch >= '0' && ch <= '9') || ch == '.' || ch == '-')
         num += ShortToString(ch);
      else if(num != "")
         break;
   }
   return StringToDouble(num);
}

//+------------------------------------------------------------------+
