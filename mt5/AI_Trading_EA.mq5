//+------------------------------------------------------------------+
//| AI_Trading_EA.mq5                                                |
//| AI Trading Platform - MetaTrader 5 Expert Advisor                |
//+------------------------------------------------------------------+
#property copyright "MT5 AI Trading Platform"
#property version   "1.00"
#property strict

#include <Trade/Trade.mqh>

//--- Inputs
input string   InpApiBaseUrl      = "https://mt5-ai.niksofts.com/api";
input string   InpApiToken        = "f346b6da84c7884e912b50ad257f40c880398071fcf739f13ea0327ceb9fa42b";
input int      InpPollIntervalSec = 7;
input int      InpCandleCount     = 50;
input double   InpMinConfidence   = 80.0;
input double   InpRiskPerTradePct = 1.0;
input int      InpMaxOpenTrades   = 3;
input string   InpSymbols         = "XAUUSD,EURUSD,GBPUSD";
input ENUM_TIMEFRAMES InpTimeframe = PERIOD_M15;
input int      InpMagicNumber     = 20260625;
input bool     InpShowButtons     = true;   // Show "Ask AI" buttons on chart

//--- Globals
#define BTN_ASK_AI    "AiBtn_AskEntry"
#define BTN_MANAGE    "AiBtn_ManagePos"

CTrade         trade;
datetime       g_lastBarTime = 0;
string         g_symbols[];
int            g_symbolCount = 0;

//+------------------------------------------------------------------+
int OnInit()
{
   trade.SetExpertMagicNumber(InpMagicNumber);
   trade.SetDeviationInPoints(20);
   trade.SetTypeFilling(ORDER_FILLING_IOC);

   if(!ParseSymbols())
   {
      Print("Failed to parse symbols: ", InpSymbols);
      return INIT_FAILED;
   }

   EventSetTimer(InpPollIntervalSec);
   Print("AI Trading EA started. Symbols: ", g_symbolCount);
   Print("API: ", InpApiBaseUrl, " | Account: ", AccountInfoInteger(ACCOUNT_LOGIN));

   // Send market data immediately (don't wait for next candle)
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
bool ParseSymbols()
{
   string parts[];
   int count = StringSplit(InpSymbols, ',', parts);
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
string Trim(string value)
{
   StringTrimLeft(value);
   StringTrimRight(value);
   return value;
}

//+------------------------------------------------------------------+
void SendMarketData()
{
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

   for(int i = 0; i < g_symbolCount; i++)
   {
      if(i > 0) json += ",";
      json += BuildSymbolJson(g_symbols[i], tf);
   }

   json += "]}";
   return json;
}

//+------------------------------------------------------------------+
string BuildSymbolJson(string symbol, string timeframe)
{
   string json = "{";
   json += "\"symbol\":\"" + symbol + "\",";
   json += "\"timeframe\":\"" + timeframe + "\",";
   json += "\"indicators\":" + BuildIndicatorsJson(symbol) + ",";
   json += "\"candles\":" + BuildCandlesJson(symbol);
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildIndicatorsJson(string symbol)
{
   int ema20Handle = iMA(symbol, InpTimeframe, 20, 0, MODE_EMA, PRICE_CLOSE);
   int ema50Handle = iMA(symbol, InpTimeframe, 50, 0, MODE_EMA, PRICE_CLOSE);
   int ema200Handle = iMA(symbol, InpTimeframe, 200, 0, MODE_EMA, PRICE_CLOSE);
   int rsiHandle = iRSI(symbol, InpTimeframe, 14, PRICE_CLOSE);
   int atrHandle = iATR(symbol, InpTimeframe, 14);

   double ema20[1], ema50[1], ema200[1], rsi[1], atr[1];
   CopyBuffer(ema20Handle, 0, 1, 1, ema20);
   CopyBuffer(ema50Handle, 0, 1, 1, ema50);
   CopyBuffer(ema200Handle, 0, 1, 1, ema200);
   CopyBuffer(rsiHandle, 0, 1, 1, rsi);
   CopyBuffer(atrHandle, 0, 1, 1, atr);

   IndicatorRelease(ema20Handle);
   IndicatorRelease(ema50Handle);
   IndicatorRelease(ema200Handle);
   IndicatorRelease(rsiHandle);
   IndicatorRelease(atrHandle);

   string json = "{";
   json += "\"ema20\":" + DoubleToString(ema20[0], _Digits) + ",";
   json += "\"ema50\":" + DoubleToString(ema50[0], _Digits) + ",";
   json += "\"ema200\":" + DoubleToString(ema200[0], _Digits) + ",";
   json += "\"rsi\":" + DoubleToString(rsi[0], 2) + ",";
   json += "\"atr\":" + DoubleToString(atr[0], _Digits);
   json += "}";
   return json;
}

//+------------------------------------------------------------------+
string BuildCandlesJson(string symbol)
{
   MqlRates rates[];
   int copied = CopyRates(symbol, InpTimeframe, 1, InpCandleCount, rates);
   string json = "[";

   for(int i = copied - 1; i >= 0; i--)
   {
      if(i < copied - 1) json += ",";
      json += "{";
      json += "\"time\":\"" + TimeToString(rates[i].time, TIME_DATE|TIME_MINUTES) + "\",";
      json += "\"open\":" + DoubleToString(rates[i].open, _Digits) + ",";
      json += "\"high\":" + DoubleToString(rates[i].high, _Digits) + ",";
      json += "\"low\":" + DoubleToString(rates[i].low, _Digits) + ",";
      json += "\"close\":" + DoubleToString(rates[i].close, _Digits) + ",";
      json += "\"volume\":" + IntegerToString((int)rates[i].tick_volume);
      json += "}";
   }

   json += "]";
   return json;
}

//+------------------------------------------------------------------+
void PollSignals()
{
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

   if(confidence < InpMinConfidence)
   {
      Print("Signal rejected: confidence ", confidence, " < ", InpMinConfidence);
      return;
   }

   if(CountOpenTrades() >= InpMaxOpenTrades)
   {
      Print("Signal rejected: max open trades reached");
      return;
   }

   if(HasOpenPosition(symbol))
   {
      Print("Signal rejected: position already open on ", symbol);
      return;
   }

   double lot = CalculateLotSize(symbol, entry, sl);
   bool success = false;
   ulong ticket = 0;

   if(action == "BUY")
      success = trade.Buy(lot, symbol, 0, sl, tp, "AI Signal #" + IntegerToString(signalId));
   else if(action == "SELL")
      success = trade.Sell(lot, symbol, 0, sl, tp, "AI Signal #" + IntegerToString(signalId));

   if(success)
   {
      ticket = trade.ResultOrder();
      Print("Trade executed: ", action, " ", symbol, " lot=", lot, " ticket=", ticket);
      NotifySignalExecuted(signalId, ticket, symbol, action, lot, entry);
   }
   else
      Print("Trade FAILED: ", action, " ", symbol, " error=", GetLastError(), " retcode=", trade.ResultRetcode());
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
   json += "\"candles\":" + BuildCandlesJson(symbol) + ",";
   json += "\"indicators\":" + BuildIndicatorsJson(symbol);
   json += "}}";
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
