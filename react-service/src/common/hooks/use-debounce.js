import { useRef, useEffect, useCallback } from "react";

/**
 * Debounce callback for React
 * @param {Function} fn - callback fn need debounce
 * @param {number} delay - waiting time (ms)
 */
export function useDebounce(fn, delay = 300) {
  const timeoutRef = useRef(null);

  const debouncedFn = useCallback(
    (...args) => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
      timeoutRef.current = setTimeout(() => {
        fn(...args);
      }, delay);
    },
    [fn, delay]
  );

  // Clear timeout when unmount
  useEffect(() => {
    return () => clearTimeout(timeoutRef.current);
  }, []);

  return debouncedFn;
}
