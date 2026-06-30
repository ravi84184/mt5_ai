<section class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><h2>AI entry pipeline</h2></div>
    <div style="padding:1rem;max-width:40rem">
        <div class="form-group">
            <label for="ai_entry_strategy">Entry strategy</label>
            <select id="ai_entry_strategy" name="ai_entry_strategy">
                @foreach(['conservative','balanced','active'] as $s)
                    <option value="{{ $s }}" @selected(old('ai_entry_strategy', $settings['ai_entry_strategy']) === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="ai_min_risk_reward">Minimum risk:reward</label>
            <input id="ai_min_risk_reward" name="ai_min_risk_reward" type="number" step="0.1" min="1" max="10"
                   value="{{ old('ai_min_risk_reward', $settings['ai_min_risk_reward']) }}">
        </div>
        <div class="form-group">
            <label for="ai_recent_candles">Candles sent to AI (slim payload)</label>
            <input id="ai_recent_candles" name="ai_recent_candles" type="number" min="5" max="50"
                   value="{{ old('ai_recent_candles', $settings['ai_recent_candles']) }}">
        </div>
        <label style="display:block;margin-bottom:0.5rem">
            <input type="checkbox" name="ai_consensus_enabled" value="1" @checked(old('ai_consensus_enabled', $settings['ai_consensus_enabled']))>
            Multi-AI consensus (requires 2+ API keys)
        </label>
        <div class="form-group">
            <label for="ai_consensus_providers">Consensus providers (comma-separated)</label>
            <input id="ai_consensus_providers" name="ai_consensus_providers" type="text"
                   value="{{ old('ai_consensus_providers', $settings['ai_consensus_providers']) }}">
        </div>
        <div class="form-group">
            <label for="ai_consensus_min_agree">Minimum agreeing models</label>
            <input id="ai_consensus_min_agree" name="ai_consensus_min_agree" type="number" min="1" max="3"
                   value="{{ old('ai_consensus_min_agree', $settings['ai_consensus_min_agree']) }}">
        </div>
    </div>
</section>

<section class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><h2>Pre-filter &amp; validator</h2></div>
    <div style="padding:1rem;max-width:40rem">
        <label style="display:block;margin-bottom:0.75rem">
            <input type="checkbox" name="pre_filter_enabled" value="1" @checked(old('pre_filter_enabled', $settings['pre_filter_enabled']))>
            Enable pre-AI filter (skip choppy setups)
        </label>
        <div class="form-group">
            <label for="pre_filter_min_adx">Minimum ADX</label>
            <input id="pre_filter_min_adx" name="pre_filter_min_adx" type="number" step="0.1"
                   value="{{ old('pre_filter_min_adx', $settings['pre_filter_min_adx']) }}">
        </div>
        <div class="form-group">
            <label for="pre_filter_min_confluence">Minimum confluence factors</label>
            <input id="pre_filter_min_confluence" name="pre_filter_min_confluence" type="number" min="1" max="10"
                   value="{{ old('pre_filter_min_confluence', $settings['pre_filter_min_confluence']) }}">
        </div>
        <label style="display:block;margin-bottom:0.75rem">
            <input type="checkbox" name="pre_filter_skip_neutral" value="1" @checked(old('pre_filter_skip_neutral', $settings['pre_filter_skip_neutral']))>
            Skip neutral weak setups
        </label>
        <div class="form-group">
            <label for="signal_max_entry_slippage_points">Max entry slippage (points)</label>
            <input id="signal_max_entry_slippage_points" name="signal_max_entry_slippage_points" type="number" min="0"
                   value="{{ old('signal_max_entry_slippage_points', $settings['signal_max_entry_slippage_points']) }}">
        </div>
    </div>
</section>

<section class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><h2>News calendar</h2></div>
    <div style="padding:1rem;max-width:40rem">
        <label style="display:block;margin-bottom:0.75rem">
            <input type="checkbox" name="news_enabled" value="1" @checked(old('news_enabled', $settings['news_enabled']))>
            Block entries around high-impact news
        </label>
        <div class="form-group">
            <label for="news_block_minutes_before">Block minutes before event</label>
            <input id="news_block_minutes_before" name="news_block_minutes_before" type="number" min="0"
                   value="{{ old('news_block_minutes_before', $settings['news_block_minutes_before']) }}">
        </div>
        <div class="form-group">
            <label for="news_block_minutes_after">Block minutes after event</label>
            <input id="news_block_minutes_after" name="news_block_minutes_after" type="number" min="0"
                   value="{{ old('news_block_minutes_after', $settings['news_block_minutes_after']) }}">
        </div>
    </div>
</section>

<section class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><h2>Telegram alerts</h2></div>
    <div style="padding:1rem;max-width:40rem">
        <label style="display:block;margin-bottom:0.75rem">
            <input type="checkbox" name="telegram_enabled" value="1" @checked(old('telegram_enabled', $settings['telegram_enabled']))>
            Enable Telegram notifications
        </label>
        <div class="form-group">
            <label for="telegram_bot_token">Bot token</label>
            <input id="telegram_bot_token" name="telegram_bot_token" type="password" autocomplete="off"
                   placeholder="{{ $settings['telegram_configured'] ? 'Configured — leave blank to keep' : 'From @BotFather' }}">
            @if ($settings['telegram_configured'])
                <label style="display:block;margin-top:0.5rem;font-size:0.875rem">
                    <input type="checkbox" name="clear_telegram_bot_token" value="1"> Remove stored token
                </label>
            @endif
        </div>
        <div class="form-group">
            <label for="telegram_chat_id">Chat ID</label>
            <input id="telegram_chat_id" name="telegram_chat_id" type="text"
                   value="{{ old('telegram_chat_id', $settings['telegram_chat_id']) }}">
        </div>
        <div class="form-group">
            <label for="telegram_daily_summary_time">Daily summary time (HH:MM)</label>
            <input id="telegram_daily_summary_time" name="telegram_daily_summary_time" type="text"
                   value="{{ old('telegram_daily_summary_time', $settings['telegram_daily_summary_time']) }}">
        </div>
        <label style="display:block"><input type="checkbox" name="telegram_notify_signals" value="1" @checked($settings['telegram_notify_signals'])> Notify accepted signals</label>
        <label style="display:block"><input type="checkbox" name="telegram_notify_trades" value="1" @checked($settings['telegram_notify_trades'])> Notify trades</label>
        <label style="display:block"><input type="checkbox" name="telegram_notify_backtests" value="1" @checked($settings['telegram_notify_backtests'])> Notify backtests</label>
        <label style="display:block"><input type="checkbox" name="telegram_notify_daily_summary" value="1" @checked($settings['telegram_notify_daily_summary'])> Daily summary</label>
    </div>
</section>

<section class="panel" style="margin-bottom:1.5rem">
    <div class="panel-header"><h2>Backtesting</h2></div>
    <div style="padding:1rem;max-width:40rem">
        <div class="form-group">
            <label for="backtest_max_bars_open">Max bars to hold simulated trade</label>
            <input id="backtest_max_bars_open" name="backtest_max_bars_open" type="number" min="10" max="500"
                   value="{{ old('backtest_max_bars_open', $settings['backtest_max_bars_open']) }}">
        </div>
    </div>
</section>
