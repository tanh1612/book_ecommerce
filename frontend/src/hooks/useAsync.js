// src/hooks/useAsync.js
import { useEffect, useRef, useCallback, useState } from 'react';

export const useAsync = (asyncFunction, immediate = true) => {
  const [state, setState] = useState({
    status: 'idle',
    value: null,
    error: null,
  });

  const abortControllerRef = useRef(null);

  const execute = useCallback(async (...args) => {
    setState({ status: 'pending', value: null, error: null });
    abortControllerRef.current = new AbortController();

    try {
      const response = await asyncFunction(...args, abortControllerRef.current.signal);
      if (!abortControllerRef.current.signal.aborted) {
        setState({ status: 'success', value: response, error: null });
        return response;
      }
    } catch (error) {
      if (error.name !== 'AbortError' && !abortControllerRef.current.signal.aborted) {
        setState({ status: 'error', value: null, error });
        throw error;
      }
    }
  }, [asyncFunction]);

  useEffect(() => {
    if (immediate) {
      execute();
    }

    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [execute, immediate]);

  return { ...state, execute };
};

export default useAsync;

