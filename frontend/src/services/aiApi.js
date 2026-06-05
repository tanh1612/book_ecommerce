// src/services/aiApi.js
import axiosClient from './axiosClient';

const aiRequestConfig = {
  timeout: 30000,
  skipGlobalErrorHandler: true,
};

const aiApi = {
  sendChat: (sessionId, question) => {
    return axiosClient.post('/v1/ai/chat', { 
      session_id: sessionId, 
      question: question 
    }, aiRequestConfig);
  },
  
  sendFeedback: (messageId, sessionId, rating) => {
    return axiosClient.post(`/v1/ai/messages/${messageId}/feedback`, { 
      session_id: sessionId, 
      rating: rating 
    }, aiRequestConfig);
  }
};

export default aiApi;
