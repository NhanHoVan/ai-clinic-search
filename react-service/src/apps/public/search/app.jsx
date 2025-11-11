import { useState, useEffect, useCallback, useRef } from "react";
import { Input, Select, List, Card, Typography, Spin, message } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";

const { Title } = Typography;

export default function App() {
	const { apiFetch } = useWordPress();
	const abortControllerRef = useRef(null);

	const [searchText, setSearchText] = useState("");
	const [loading, setLoading] = useState(false);
	const [clinicsSearch, setClinicsSearch] = useState([]);

	const [provinces, setProvinces] = useState([]);
	const [districts, setDistricts] = useState([]);
	const [selectedProvince, setSelectedProvince] = useState("");
	const [selectedDistrict, setSelectedDistrict] = useState("");

	const fetchProvinces = useCallback(
		async (search = "") => {
			const data = await apiFetch("/provinces", {
				query: search ? { search } : {},
			});
			setProvinces(data);
		},
		[apiFetch]
	);

	const fetchDistricts = useCallback(
		async (search = "") => {
			const data = await apiFetch("/districts", {
				query: {
					...(selectedProvince
						? { province_code: selectedProvince }
						: {}),
					...(search ? { search } : {}),
				},
			});
			setDistricts(data);
		},
		[apiFetch, selectedProvince]
	);

	const fetchClinicsSearch = useCallback(
		async (search = "") => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			abortControllerRef.current = new AbortController();

			try {
				const data = await apiFetch("/clinics/search", {
					query: {
						...(search ? { search } : {}),
						...(selectedProvince
							? { province: selectedProvince }
							: {}),
						...(selectedDistrict
							? { district: selectedDistrict }
							: {}),
					},
					signal: abortControllerRef.current.signal,
				});
				setClinicsSearch(data);
			} catch (error) {
				if (error.name !== "AbortError") {
					throw error;
				}
			}
		},
		[apiFetch, selectedProvince, selectedDistrict]
	);

	useEffect(() => {
		fetchProvinces().catch((e) => message.error(e?.message));
	}, [fetchProvinces]);

	useEffect(() => {
		if (!selectedProvince) {
			setDistricts([]);
			setSelectedDistrict("");
			return;
		}

		fetchDistricts().catch((e) => message.error(e?.message));
	}, [selectedProvince, fetchDistricts]);

	useEffect(() => {
		return () => {
			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}
		};
	}, []);

	const handleClinicsSearch = async (search) => {
		setSearchText(search);

		const isEmptySearch = !search?.trim();
		const hasFilters = selectedProvince || selectedDistrict;

		if (isEmptySearch && !hasFilters) {
			setClinicsSearch([]);
			return;
		}

		setLoading(true);

		try {
			await fetchClinicsSearch(search);
		} catch (e) {
			if (e?.name !== "AbortError") {
				message.error(e?.message);
				setClinicsSearch([]);
			}
		} finally {
			setLoading(false);
		}
	};

	const handleProvinceChange = (value) => {
		setSelectedProvince(value);
		setSelectedDistrict("");
		setDistricts([]);
		setSearchText("");
	};

	const handleDistrictChange = (value) => {
		setSelectedDistrict(value);
		setSearchText("");
	};

	return (
		<div style={{ margin: "40px auto" }}>
			<Title level={2} style={{ textAlign: "center" }}>
				🔍 AI Clinic Search
			</Title>

			<div style={{ display: "flex", gap: 10, marginBottom: 20 }}>
				<Select
					showSearch
					placeholder="Select Province"
					style={{ width: 200 }}
					size="large"
					value={selectedProvince || undefined}
					onChange={handleProvinceChange}
					filterSort={(a, b) =>
						(a?.label ?? "").localeCompare(b?.label ?? "")
					}
					allowClear
					optionFilterProp="label"
					options={provinces.map((p) => ({
						label: p.name,
						value: p.code,
					}))}
					disabled={loading}
				/>

				<Select
					showSearch
					placeholder="Select District"
					style={{ width: 200 }}
					size="large"
					value={selectedDistrict || undefined}
					onChange={handleDistrictChange}
					disabled={loading || !districts.length}
					filterSort={(a, b) =>
						(a?.label ?? "").localeCompare(b?.label ?? "")
					}
					allowClear
					optionFilterProp="label"
					options={districts.map((d) => ({
						label: d.name,
						value: d.code,
					}))}
				/>

				<Input.Search
					value={searchText}
					onChange={(e) => setSearchText(e.target.value)}
					placeholder="Enter hospital, doctor, or location..."
					allowClear
					enterButton="Search"
					size="large"
					onSearch={handleClinicsSearch}
					loading={loading}
					style={{ flex: 2 }}
				/>
			</div>

			<div style={{ marginTop: 30 }}>
				{loading ? (
					<Spin
						size="large"
						style={{ display: "block", margin: "50px auto" }}
					/>
				) : clinicsSearch.length > 0 ? (
					<>
						<Typography.Text
							type="secondary"
							style={{ marginBottom: 16, display: "block" }}
						>
							Found {clinicsSearch.length} results
							{searchText && ` for "${searchText}"`}
						</Typography.Text>
						<List
							grid={{ gutter: 16, column: 1 }}
							dataSource={clinicsSearch}
							renderItem={(item) => (
								<List.Item>
									<Card
										title={
											<a
												href={item.link}
												target="_blank"
												rel="noopener noreferrer"
												dangerouslySetInnerHTML={{
													__html: item.title.rendered,
												}}
											/>
										}
										hoverable
									>
										<div
											dangerouslySetInnerHTML={{
												__html: item.excerpt.rendered,
											}}
										/>
									</Card>
								</List.Item>
							)}
						/>
					</>
				) : (
					<Card>
						<Typography.Text type="secondary">
							No results found.
						</Typography.Text>
					</Card>
				)}
			</div>
		</div>
	);
}
