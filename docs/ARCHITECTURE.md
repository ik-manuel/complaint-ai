# System Architecture

## Overview

ComplaintAI follows a service-oriented architecture with clear separation of concerns.

## Architecture Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                    COMPLAINT SUBMISSION                      │
│  Customer submits complaint → Stored in DB                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              AI CLASSIFICATION (Week 2)                      │
│  • Few-shot prompting (Temp 0.1)                            │
│  • Detects: Urgency + Category                              │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│         CONVERSATION CREATION (Week 3)                       │
│  • Creates conversation record                               │
│  • Sets role-based system prompt                             │
│  • Stores initial message                                    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│           INITIAL AI RESPONSE (Week 2)                       │
│  • Role-based prompting (Temp 0.3)                          │
│  • Tone matches urgency                                      │
│  • Saved to conversation history                             │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              FOLLOW-UP MESSAGES (Week 3)                     │
│  • Customer sends follow-up                                  │
│  • System retrieves conversation history                     │
│  • Dynamic window size (10-25 messages)                      │
│  • Includes summary if exists                                │
│  • AI responds with full context                             │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│         AUTO-SUMMARIZATION (Week 3 Advanced)                 │
│  • Triggers at 15, 30, 45... messages                       │
│  • First time: Summarize all old messages                    │
│  • Re-summarization: Only new messages (smart!)              │
│  • Merges with existing summary                              │
│  • Excludes recent window (no redundancy)                    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              ADMIN REVIEW & APPROVAL                         │
│  • View full conversation thread                             │
│  • See summary + individual messages                         │
│  • Token usage tracking                                      │
│  • Edit/approve responses                                    │
│  • Mark as resolved                                          │
└─────────────────────────────────────────────────────────────┘
```

## Service Layer

### GroqService
**Responsibility**: Handle all Groq API communication  
**Methods**:
- `chat(prompt, options)`: Make API requests
- Error handling and retry logic
- Token tracking

### ComplaintClassifier
**Responsibility**: Analyze and categorize complaints  
**AI Technique**: Few-shot learning  
**Temperature**: 0.1 (deterministic)  
**Output**: Urgency level + Category

### ResponseGenerator
**Responsibility**: Generate empathetic responses  
**AI Technique**: Role-based prompting  
**Temperature**: 0.3 (balanced creativity)  
**Output**: Professional response text

## Data Flow

1. Customer submits complaint
2. System creates/finds customer record
3. Complaint saved to database
4. AI classifies complaint (urgency + category)
5. AI generates response based on classification
6. Admin reviews and approves
7. Response sent to customer (future enhancement)

## AI Decision Matrix

| Urgency | System Role | Tone | Use Case |
|---------|-------------|------|----------|
| Low | Friendly Support Rep | Warm, patient | Questions, minor issues |
| Medium | Professional Agent | Clear, solution-focused | Standard complaints |
| High | Senior Manager | Direct, empathetic | Urgent problems, angry customers |