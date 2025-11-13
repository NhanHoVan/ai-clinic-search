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
	 * @param {object} options - method, headers, body, params, signal
	 */
	const apiFetch = useCallback(
		async (endpoint, options = {}) => {
			const {
				params,
				method = "POST",
				headers: customHeaders,
				signal,
				body: customBody,
				...restOptions
			} = options;

			const normalizedMethod = method.toUpperCase();
			const headers = {
				"X-WP-Nonce": config.nonce,
				...customHeaders,
			};
			const fetchOptions = {
				method: normalizedMethod,
				headers,
				signal,
				...restOptions,
			};

			const url = new URL(
				`${apiBase}${endpoint}`,
				window.location.origin
			);

			const methodsWithBody = ["POST", "PUT", "PATCH", "DELETE"];
			if (methodsWithBody.includes(normalizedMethod)) {
				const payload = customBody || params || null;

				if (!customHeaders?.["Content-Type"]) {
					if (
						!(customBody instanceof FormData) &&
						!(params instanceof FormData)
					) {
						headers["Content-Type"] = "application/json";
					}
				}

				if (
					headers["Content-Type"] === "application/json" &&
					payload !== null
				) {
					fetchOptions.body = JSON.stringify(payload);
				} else {
					fetchOptions.body = payload;
				}
			} else if (normalizedMethod === "GET") {
				if (params && Object.keys(params).length > 0) {
					url.searchParams.append("params", JSON.stringify(params));
				}
			}

			try {
				const response = await fetch(url.toString(), fetchOptions);

				if (!response.ok) {
					let errorMessage = `API Error: ${response.status}`;
					let errorCode = null;

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
						// Response is not JSON
					}

					const error = new Error(errorMessage);
					error.status = response.status;
					error.code = errorCode;
					throw error;
				}

				return response.json();
			} catch (error) {
				if (error.name === "AbortError") throw error;

				if (error instanceof TypeError) {
					throw new Error(
						"Network error. Please check your connection."
					);
				}

				throw error;
			}
		},
		[apiBase, config.nonce]
	);

	return { config, apiBase, apiFetch };
}
