# System Architecture

## Overview

ComplaintAI follows a service-oriented architecture with clear separation of concerns.

## Architecture Diagram
```
┌─────────────────┐
│  Public Form    │
│  (Customer)     │
└────────┬────────┘
         │ POST /complaints
         ▼
┌─────────────────────────────┐
│  ComplaintController        │
│  • Validates input          │
│  • Creates customer/complaint│
└────────┬────────────────────┘
         │
         ├─> ComplaintClassifier (AI)
         │   └─> GroqService (API)
         │       • Temperature: 0.1
         │       • Few-shot prompting
         │
         └─> ResponseGenerator (AI)
             └─> GroqService (API)
                 • Temperature: 0.3
                 • Role-based prompting
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