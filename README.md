# ComplaintAI - AI-Powered Customer Complaint Management System

![Laravel](https://img.shields.io/badge/Laravel-10.x-red)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![AI](https://img.shields.io/badge/AI-Groq%20%7C%20Llama%203.1-green)
![License](https://img.shields.io/badge/license-MIT-blue)

An intelligent customer complaint management system that leverages Large Language Models (LLMs) to automatically classify complaints and generate professional, context-aware responses.

## 🎯 Project Overview

ComplaintAI demonstrates practical application of AI/LLM concepts in a real-world customer service scenario. Built as a learning project to showcase AI integration skills with Laravel.

### **Key Features**

- 🤖 **AI-Powered Classification**: Automatically categorizes complaints by urgency (low/medium/high) and type (billing, shipping, product quality, technical, other)
- ✍️ **Intelligent Response Generation**: Creates personalized, empathetic responses using role-based prompting
- 📊 **Admin Dashboard**: Review, edit, and approve AI-generated responses before sending
- 📈 **Analytics**: Track complaint statistics, urgency levels, and resolution rates
- ⚡ **Real-time Processing**: Instant AI analysis upon complaint submission
- 🎨 **Modern UI**: Clean, responsive interface built with Tailwind CSS

## 🧠 AI Concepts Demonstrated

This project showcases fundamental AI engineering concepts:

### **Week 1 Concepts: LLM Fundamentals**
- API integration with Groq (Llama 3.3 70B)
- Temperature control (0.1 for classification, 0.3 for generation)
- Token usage tracking and optimization
- Error handling and retry logic
- Cost awareness in production

### **Week 2 Concepts: Prompt Engineering**
- **Few-shot learning**: Teaching classification through examples
- **Role-based prompting**: Different system messages for urgency levels
- **Structured outputs**: Parsing AI responses into actionable data
- **Context management**: Providing relevant customer information
- **Tone adaptation**: Matching response style to urgency

## 🛠️ Tech Stack

- **Framework**: Laravel 12.x
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0
- **AI API**: Groq (Llama 3.3 70B Versatile)
- **Frontend**: Blade Templates + Tailwind CSS
- **HTTP Client**: Guzzle (via Laravel HTTP)

## 📋 Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Groq API Key ([Get one free](https://console.groq.com))

## 🚀 Installation

### 1. Clone the repository
```bash
git clone https://github.com/ik-manuel/complaint-ai.git
cd complaint-ai
```

### 2. Install dependencies
```bash
composer install
```

### 3. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Update `.env` with your credentials
```env
DB_DATABASE=complaint_ai
DB_USERNAME=your_username
DB_PASSWORD=your_password

GROQ_API_KEY=your_groq_api_key_here
GROQ_MODEL=llama-3.3-70b-versatile
```

### 5. Create database
```bash
mysql -u root -p
CREATE DATABASE complaint_ai;
exit;
```

### 6. Run migrations
```bash
php artisan migrate
```

### 7. (Optional) Seed demo data
```bash
php artisan db:seed
```

### 8. Start the server
```bash
php artisan serve
```

Visit: `http://localhost:8000`

## 📖 Usage

### **For Customers**

1. Navigate to the homepage
2. Fill in your details and complaint message
3. Submit and receive a ticket number
4. AI analyzes your complaint instantly
5. Receive response after admin approval

### **For Admins**

1. Visit `/admin` dashboard
2. Review AI-classified complaints
3. See AI-generated responses
4. Edit responses if needed
5. Approve and send to customer
6. Mark as resolved when complete

## 🎨 Screenshots

### Customer Submission Form
![Submission Form](docs/screenshots/submission-form.jpg)

### AI Classification Result
![Classification](docs/screenshots/classification.jpg)

### Admin Dashboard
![Dashboard](docs/screenshots/dashboard.jpg)

### AI Response Review
![Response Review](docs/screenshots/response-review.jpg)
![Response Review](docs/screenshots/response-approved.jpg)

## 🧪 How It Works

### **1. Complaint Classification**

Uses few-shot prompting with temperature 0.1 for consistent results:
```php
$prompt = "Classify this customer complaint.

Examples:
Complaint: \"My order hasn't arrived in 3 weeks! This is unacceptable!\"
Urgency: high
Category: shipping

Now classify:
Subject: {$subject}
Message: {$message}";
```

### **2. Response Generation**

Applies role-based prompting with temperature 0.3 for balanced creativity:
```php
// Different system messages for each urgency level
$systemMessage = match($urgency) {
    'high' => "You are a senior customer service manager handling urgent complaints. 
               Be direct, empathetic, and action-oriented.",
    'medium' => "You are a professional customer support agent. 
                 Be helpful, clear, and solution-focused.",
    'low' => "You are a friendly customer support representative. 
              Be warm, patient, and informative."
};
```

## 📊 Database Schema
```
customers
├─ id
├─ name
├─ email
└─ created_at

complaints
├─ id
├─ customer_id (FK)
├─ ticket_number
├─ subject
├─ message
├─ urgency (low/medium/high)
├─ category
├─ status (new/responded/resolved)
└─ created_at

ai_responses
├─ id
├─ complaint_id (FK)
├─ response_text
├─ tokens_used
├─ approved (boolean)
└─ created_at
```

## 🔧 Configuration

### AI Settings (`.env`)
```env
GROQ_API_KEY=your_key_here
GROQ_MODEL=llama-3.3-70b-versatile

# Optional: Adjust AI behavior
AI_CLASSIFICATION_TEMPERATURE=0.1
AI_GENERATION_TEMPERATURE=0.3
AI_MAX_TOKENS=500
```

## 📈 Performance & Costs

**Average Processing:**
- Classification: ~50-100 tokens
- Response generation: ~200-300 tokens
- Total per complaint: ~300-400 tokens

**Costs (Groq Free Tier):**
- ✅ Free: 14,400 requests/day
- ✅ Plenty for learning and demos
- ✅ Production: Minimal cost (~$0.01 per complaint on paid tiers)

## 🧪 Testing
```bash
# Run tests
php artisan test

# Test AI integration specifically
php artisan test --filter=AIIntegrationTest
```

## 🚧 Roadmap (Future Enhancements)

- [x] **Week 3**: Add conversation memory for follow-ups
- [ ] **Week 4**: Implement function calling for database queries
- [ ] **Week 8**: RAG system for policy document retrieval
- [ ] Multi-language support
- [ ] Email integration (auto-send responses)
- [ ] Sentiment analysis visualization
- [ ] Customer satisfaction tracking
- [ ] API for third-party integrations

---

## 🎊 Week 3 Final Assessment

**Technical Mastery:**

| Concept | Level | Evidence |
|---------|-------|----------|
| Conversation Memory | ⭐⭐⭐⭐⭐ | Multi-turn working perfectly |
| Token Optimization | ⭐⭐⭐⭐⭐ | 17% reduction achieved |
| Database Design | ⭐⭐⭐⭐⭐ | Normalized, efficient schema |
| Production Thinking | ⭐⭐⭐⭐⭐ | Cost-aware, scalable |
| Debugging Skills | ⭐⭐⭐⭐⭐ | Found 2 bugs independently |
| Architecture | ⭐⭐⭐⭐⭐ | Service-oriented, clean |

**Overall Grade: A++** 🏆🏆🏆

---

## 📚 Week 3 → Week 4 Bridge

**What you've mastered:**
- ✅ Week 1: LLM API basics
- ✅ Week 2: Prompt engineering
- ✅ Week 3: Conversation memory + Advanced optimization

**Week 4 Preview: Function Calling / Tool Use**
```
Current capability:
User: "What's my order status for #12345?"
AI: "I don't have access to your order database." ❌

Week 4 capability:
User: "What's my order status for #12345?"
AI: *calls database* → "Order #12345 shipped yesterday, arrives tomorrow" ✅
```

**Next:**
- Function/tool calling
- Structured JSON outputs
- Database queries via AI
- Weather, calculator, and custom tools
- Building AI that can "take actions"

---

## 💬 Week 3 Completion Celebration:
```
🎉 Completion status: [EXCEEDED EXPECTATIONS]
📊 Projects on GitHub: [3 - llm-basics + ai-chat-api + complaint-ai]
🏆 Advanced features added: [2 - both working!]
💡 Bugs found and fixed: [3 total]
🎯 Production readiness: [100%]
📈 Token optimization achieved: [17%]
🔥 Excitement for Week 4: [10]
```

## 🤝 Contributing

This is a learning project, but contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📚 Learning Resources

This project was built while following an AI Engineering learning path:

- [OpenAI API Documentation](https://platform.openai.com/docs)
- [Groq Documentation](https://console.groq.com/docs)
- [Anthropic Prompt Engineering Guide](https://docs.anthropic.com/en/docs/prompt-engineering)
- [Laravel Documentation](https://laravel.com/docs)

## 📝 License

This project is open-sourced under the MIT License. See [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Ikechukwu A. Manuel**  
- GitHub: [@ik-manuel](https://github.com/ik-manuel)
- LinkedIn: [Ikechukwu Anigbata](https://linkedin.com/in/ik-manuel)
- Email: williamikechukwu@gmail.com

## 🙏 Acknowledgments

- Built with [Groq](https://groq.com) for fast LLM inference
- Powered by [Llama 3.3](https://ai.meta.com/llama/) from Meta
- UI components styled with [Tailwind CSS](https://tailwindcss.com)

---

**⭐ If you found this project helpful, please give it a star!**

Built with ❤️ as part of AI Engineering learning journey
