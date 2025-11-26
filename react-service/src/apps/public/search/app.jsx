import { useState, useEffect, useCallback, useRef } from "react";
import {
	Input,
	Select,
	List,
	Card,
	Typography,
	Spin,
	message,
	Row,
	Col,
	Pagination,
	Image,
} from "antd";
import { CopyOutlined, EyeOutlined } from "@ant-design/icons";
import { maskPhone, maskEmail, getStatusIcon } from "@/utils/utils";
import { IMAGE_FALLBACK } from "@/utils/image";
import { useWordPress } from "@/hooks/use-wordpress";

// import clinics from "./dummy_clinics.json";

const { Title } = Typography;
const { Meta } = Card;

export default function App() {
	const { apiFetch } = useWordPress();
	const abortControllerRef = useRef(null);

	const [searchText, setSearchText] = useState("");
	const [loadingSearch, setLoadingSearch] = useState(false);
	const [clinicsSearch, setClinicsSearch] = useState([]);

	const [page, setPage] = useState(1);
	const [limit] = useState(8);
	const [total, setTotal] = useState(0);
	const [clinicsRefer, setClinicsRefer] = useState([]);
	const [loadingRefer, setLoadingRefer] = useState(false);

	const [provinces, setProvinces] = useState([]);
	const [districts, setDistricts] = useState([]);
	const [selectedProvince, setSelectedProvince] = useState("");
	const [selectedDistrict, setSelectedDistrict] = useState("");

	const fetchProvinces = useCallback(
		async (search = "") => {
			const data = await apiFetch("/provinces", {
				method: "GET",
				params: search ? { search } : {},
			});
			setProvinces(Array.isArray(data) ? data : []);
		},
		[apiFetch]
	);

	const fetchDistricts = useCallback(
		async (search = "") => {
			if (!selectedProvince) {
				throw new Error("Missing required parameters");
			}

			const data = await apiFetch("/districts", {
				body: {
					province_code: selectedProvince,
					...(search ? { search } : {}),
				},
			});
			setDistricts(Array.isArray(data) ? data : []);
		},
		[apiFetch, selectedProvince]
	);

	const fetchClinicsSearch = useCallback(
		async (search = "") => {
			if (!search || !selectedProvince || !selectedDistrict) {
				throw new Error("Missing required parameters");
			}

			if (abortControllerRef.current) {
				abortControllerRef.current.abort();
			}

			abortControllerRef.current = new AbortController();

			try {
				const data = await apiFetch("/clinics/search", {
					body: {
						search,
						province: selectedProvince,
						district: selectedDistrict,
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

	const fetchClinicsRefer = useCallback(
		async (page = 1) => {
			const data = await apiFetch("/clinics", {
				body: {
					province: selectedProvince,
					district: selectedDistrict,
					page,
					limit,
				},
			});

			setClinicsRefer(data.data || []);
			setTotal(data.total || 0);
			setPage(data.page || page);
		},
		[apiFetch, selectedProvince, selectedDistrict]
	);

	const handleClinicsRefer = async (page) => {
		setLoadingRefer(true);

		try {
			await fetchClinicsRefer(page);
		} catch (e) {
			message.error(e?.message);
		} finally {
			setLoadingRefer(false);
		}
	};

	useEffect(() => {
		// setClinicsRefer(clinics);
		handleClinicsRefer(1);
	}, [selectedProvince, selectedDistrict]);

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

		setLoadingSearch(true);

		try {
			await fetchClinicsSearch(search);
		} catch (e) {
			if (e?.name !== "AbortError") {
				message.error(e?.message);
				setClinicsSearch([]);
			}
		} finally {
			setLoadingSearch(false);
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

	const handleClickCopy = (clinicName) => {
		// TODO update handle for copy clinic infor
		message.success(`Đã copy đường dẫn tới phòng khám: ${clinicName}`);
	};

	return (
		<div style={{ margin: "40px auto" }}>
			<Title level={2} style={{ textAlign: "center" }}>
				🔍 AI Clinic Search
			</Title>

			<div style={{ display: "flex", gap: 10, marginBottom: 20 }}>
				<div className="flex-col-w200">
					<span className="label-input">Chọn tỉnh/thành phố</span>
					<Select
						showSearch
						placeholder="Select Province"
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
						disabled={loadingSearch}
					/>
				</div>

				<div className="flex-col-w200">
					<span className="label-input">Chọn xã/phường</span>
					<Select
						showSearch
						placeholder="Select District"
						size="large"
						value={selectedDistrict || undefined}
						onChange={handleDistrictChange}
						disabled={loadingSearch || !districts.length}
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
				</div>

				<div className="flex-col-fl2">
					<span className="label-input">
						Nhập tên phòng khám, từ khóa cần tìm
					</span>
					<Input.Search
						value={searchText}
						onChange={(e) => setSearchText(e.target.value)}
						placeholder="Enter hospital, doctor, or location..."
						allowClear
						enterButton="Search"
						size="large"
						onSearch={handleClinicsSearch}
						loading={loadingSearch}
					/>
				</div>
			</div>

			<div style={{ marginTop: 30 }}>
				{loadingSearch ? (
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
							Tìm thấy {clinicsSearch.length} kết quả
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
					<Card loading={loadingSearch}>
						<Typography.Text type="secondary">
							Không tìm thấy thông tin nào!
						</Typography.Text>
					</Card>
				)}
			</div>

			{clinicsRefer.length > 0 ? (
				<div style={{ marginTop: 30 }}>
					<Title
						level={3}
						style={{ textAlign: "left", marginBottom: 20 }}
					>
						Tham khảo các địa chỉ khác gần bạn:
					</Title>
					<Row gutter={[16, 16]}>
						{clinicsRefer.map((clinic, index) => (
							<Col key={index} xs={24} sm={12} md={12} lg={6}>
								<Card
									cover={
										<Image
											style={{ objectFit: "cover" }}
											height={200}
											draggable={false}
											alt={clinic.name}
											src={clinic.img_cover}
											fallback={IMAGE_FALLBACK}
										/>
									}
									actions={[
										<CopyOutlined
											key="share"
											style={{ color: "#4ecdc4" }}
											onClick={() =>
												handleClickCopy(clinic.name)
											}
										/>,
										<EyeOutlined
											key="detail"
											style={{ color: "#4e50cdff" }}
										/>,
									]}
									hoverable
									loading={loadingRefer}
								>
									<Meta
										title={
											<span>
												{getStatusIcon(
													clinic.verify_status
												)}{" "}
												{clinic.name}
											</span>
										}
										description={
											clinic.description && (
												<p>{clinic.description}</p>
											)
										}
									/>
									<p>
										<strong>Địa chỉ:</strong>{" "}
										{clinic.address}
									</p>
									{clinic.phone && (
										<p>
											<strong>Điện thoại:</strong>{" "}
											{maskPhone(clinic.phone)}
										</p>
									)}
									{clinic.email && (
										<p>
											<strong>Email:</strong>{" "}
											{maskEmail(clinic.email)}
										</p>
									)}
								</Card>
							</Col>
						))}
					</Row>
					<Pagination
						style={{ marginTop: 20 }}
						align="center"
						current={page}
						pageSize={limit}
						total={total}
						onChange={(p) => handleClinicsRefer(p)}
					/>
				</div>
			) : null}
		</div>
	);
}
