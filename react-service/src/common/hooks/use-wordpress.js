import { useMemo, useCallback } from "react";

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

	const apiBase = `${config.restUrl}ai-clinic-search/proxy/v1`;

	/**
	 * Call API WordPress REST via Proxy.
	 * @param {string} endpoint - Ex: "/provinces"
	 * @param {object} options - method, headers, body, query, signal
	 */
	const apiFetch = useCallback(
		async (endpoint, options = {}) => {
			const { query, headers: customHeaders, ...restOptions } = options;

			// Build query string
			let url = `${apiBase}${endpoint}`;
			if (
				query &&
				typeof query === "object" &&
				Object.keys(query).length > 0
			) {
				const params = new URLSearchParams();
				Object.entries(query).forEach(([key, value]) => {
					if (value === undefined || value === null || value === "")
						return;

					// Handle array parameters
					if (Array.isArray(value)) {
						value.forEach((v) =>
							params.append(`${key}[]`, String(v))
						);
					} else {
						params.append(key, String(value));
					}
				});

				const queryString = params.toString();
				if (queryString) {
					url += `?${queryString}`;
				}
			}

			// Build headers
			const headers = {
				"X-WP-Nonce": config.nonce,
				...customHeaders,
			};

			// Auto set Content-Type for JSON bodies
			if (!customHeaders?.["Content-Type"] && restOptions.body) {
				if (!(restOptions.body instanceof FormData)) {
					headers["Content-Type"] = "application/json";
				}
			}

			try {
				const response = await fetch(url, {
					...restOptions,
					headers,
				});

				if (!response.ok) {
					let errorMessage = `API Error: ${response.status}`;
					let errorCode = null;

					// Try to get error message from response
					try {
						const errorData = await response.json();
						errorMessage =
							errorData.message ||
							errorData.error ||
							errorMessage;
						errorCode = errorData.code;

						if (response.status === 429) {
							errorMessage =
								"Too many requests. Please try again later.";
						}
					} catch {
						// Response is not JSON, use default message
					}

					const error = new Error(errorMessage);
					error.status = response.status;
					error.code = errorCode;
					throw error;
				}

				return response.json();
			} catch (error) {
				// Re-throw AbortError for proper handling in components
				if (error.name === "AbortError") {
					throw error;
				}

				// Handle network errors
				if (error instanceof TypeError) {
					throw new Error(
						"Network error. Please check your connection."
					);
				}

				// Re-throw other errors
				throw error;
			}
		},
		[apiBase, config.nonce]
	);

	return { config, apiBase, apiFetch };
}
