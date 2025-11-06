import { useMemo } from "react";

export function useWordPress() {
  const config = useMemo(() => {
    return (
      window.aiReactConfig || {
        restUrl: "/wp-json/",
        siteUrl: "/",
        nonce: "",
        appKey: "unknown",
      }
    );
  }, []);

  const apiFetch = async (endpoint, options = {}) => {
    const url = `${config.restUrl}${endpoint}`;
    const headers = {
      "X-WP-Nonce": config.nonce,
      "Content-Type": "application/json",
      ...options.headers,
    };

    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (!response.ok) {
      throw new Error(`API Error: ${response.status}`);
    }

    return response.json();
  };

  return {
    config,
    apiFetch,
  };
}
