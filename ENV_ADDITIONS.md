# Environment File Additions

## Add to .env.example

Add these lines to your `.env.example` file:

```env
# AI Configuration
AI_DEFAULT_PROVIDER=cerebrus
AI_LOGGING_ENABLED=true
AI_RATE_LIMITING_ENABLED=true
AI_RATE_LIMIT_PER_MINUTE=60
AI_RATE_LIMIT_PER_HOUR=1000

# Cerebrus AI
CEREBRUS_API_KEY=
CEREBRUS_BASE_URL=https://api.cerebrus.ai
CEREBRUS_DEFAULT_MODEL=gpt-4
CEREBRUS_TIMEOUT=30
CEREBRUS_MAX_TOKENS=4000
CEREBRUS_TEMPERATURE=0.7

# OpenAI (Optional)
OPENAI_API_KEY=
OPENAI_ORGANIZATION=
OPENAI_DEFAULT_MODEL=gpt-4
OPENAI_TIMEOUT=30
OPENAI_MAX_TOKENS=4000
OPENAI_TEMPERATURE=0.7

# Anthropic (Optional)
ANTHROPIC_API_KEY=
ANTHROPIC_DEFAULT_MODEL=claude-3-sonnet-20240229
ANTHROPIC_TIMEOUT=30
ANTHROPIC_MAX_TOKENS=4000
ANTHROPIC_TEMPERATURE=0.7

# Google Gemini (Optional)
GEMINI_API_KEY=
GEMINI_DEFAULT_MODEL=gemini-pro
GEMINI_TIMEOUT=30
GEMINI_MAX_TOKENS=4000
GEMINI_TEMPERATURE=0.7

# AI Features
AI_TASK_GENERATION_ENABLED=true
AI_TASK_GENERATION_PROVIDER=cerebrus
AI_TASK_GENERATION_MAX_TASKS=10

AI_PROJECT_ANALYSIS_ENABLED=true
AI_PROJECT_ANALYSIS_PROVIDER=cerebrus

AI_TASK_SUGGESTIONS_ENABLED=true
AI_TASK_SUGGESTIONS_PROVIDER=cerebrus

# AI Caching (Optional)
AI_CACHING_ENABLED=false
AI_CACHE_DRIVER=redis
AI_CACHE_TTL=3600
```

## Add to .env

Add the same content to your `.env` file, but with your actual Cerebrus API key:

```env
# AI Configuration
AI_DEFAULT_PROVIDER=cerebrus
AI_LOGGING_ENABLED=true
AI_RATE_LIMITING_ENABLED=true
AI_RATE_LIMIT_PER_MINUTE=60
AI_RATE_LIMIT_PER_HOUR=1000

# Cerebrus AI - ADD YOUR ACTUAL API KEY HERE
CEREBRUS_API_KEY=your_actual_cerebrus_api_key_here
CEREBRUS_BASE_URL=https://api.cerebrus.ai
CEREBRUS_DEFAULT_MODEL=gpt-4
CEREBRUS_TIMEOUT=30
CEREBRUS_MAX_TOKENS=4000
CEREBRUS_TEMPERATURE=0.7

# ... (rest of the configuration as above)
```

## 🔧 Quick Setup

1. **Copy the environment variables** from above into your `.env.example` and `.env` files
2. **Replace `your_actual_cerebrus_api_key_here`** with your real Cerebrus API key in `.env`
3. **Test the connection**: `php artisan ai:test cerebrus`
4. **Create a project** to see AI task generation in action

## 📝 Notes

- The `.env` file should contain your actual API keys
- The `.env.example` file should have empty values as templates
- Only the Cerebrus provider is fully implemented
- Other providers are configured but not yet implemented
- The system gracefully falls back to static tasks if AI fails
