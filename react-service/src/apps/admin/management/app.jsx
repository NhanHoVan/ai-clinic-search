import { useState, useEffect, useCallback, useRef } from "react";
import { Typography, message, Table, Button, Space } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";
import { useLocationPreference } from "@/hooks/use-location-preference";
import SearchHeader from "@/component/search-header.jsx";

const { Title, Text } = Typography;

export default function App() {
	const { apiFetch } = useWordPress();

	const [searchText, setSearchText] = useState("");
	const [page, setPage] = useState(1);
	const [limit] = useState(20);
	const [total, setTotal] = useState(0);
	const [clinicsRefer, setClinicsRefer] = useState([]);
	const [loadingRefer, setLoadingRefer] = useState(false);

	const [provinces, setProvinces] = useState([]);
	const [districts, setDistricts] = useState([]);
	const { pref, setProvince, setDistrict } = useLocationPreference();
	const [selectedProvince, setSelectedProvince] = useState();
	const [selectedDistrict, setSelectedDistrict] = useState();

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

	const fetchClinicsRefer = useCallback(
		async ({ page, search }) => {
			const data = await apiFetch("/clinics", {
				body: {
					province: selectedProvince,
					district: selectedDistrict,
					search,
					page,
					limit,
				},
			});

			setClinicsRefer(data.clinics || []);
			setTotal(data.total || 0);
			setPage(page);
		},
		[apiFetch, selectedProvince, selectedDistrict, limit]
	);

	const loadClinics = async ({ page = 1, search = searchText }) => {
		setLoadingRefer(true);
		try {
			await fetchClinicsRefer({ page, search });
		} catch (e) {
			message.error(e?.message || "Không thể tải danh sách phòng khám");
		} finally {
			setLoadingRefer(false);
		}
	};

	const handleClinicsSearch = () => {
		loadClinics({ page: 1, search: searchText });
	};

	const handleTableChange = (pagination) => {
		loadClinics({
			page: pagination.current,
		});
	};

	useEffect(() => {
		handleClinicsSearch();
	}, []);

	useEffect(() => {
		fetchProvinces().catch((e) => message.error(e?.message));
	}, [fetchProvinces]);

	useEffect(() => {
		if (!provinces.length || selectedProvince) return;

		if (pref.province) {
			setSelectedProvince(pref.province);
			return;
		}
	}, [provinces]);

	useEffect(() => {
		if (pref.district) {
			setSelectedDistrict(pref.district);
		}
	}, [districts]);

	useEffect(() => {
		if (!selectedProvince) {
			setDistricts([]);
			setDistrict(null);
			setSelectedDistrict("");
			return;
		}

		fetchDistricts().catch((e) => message.error(e?.message));
	}, [selectedProvince, fetchDistricts]);

	const handleProvinceChange = (value) => {
		setSelectedProvince(value);
		setProvince(value);
		setSelectedDistrict("");
		setDistricts([]);
		setSearchText("");
	};

	const handleDistrictChange = (value) => {
		setSelectedDistrict(value);
		setDistrict(value);
		setSearchText("");
	};

	const handleChangeText = (value) => {
		setSearchText(value);
	};

	const handleSuggestEdit = (record) => {
		// TODO update handle for copy clinic infor
		message.success(`Đề xuất chỉnh sửa: ${record.name}`);
	};

	const columns = [
		{
			title: "ID",
			dataIndex: "id",
			key: "id",
			width: 50,
		},
		{
			title: "Tên phòng khám",
			dataIndex: "name",
			key: "name",
			width: 250,
			render: (text) => <Text strong>{text}</Text>,
			ellipsis: true,
		},
		{
			title: "Địa chỉ",
			dataIndex: "address",
			key: "address",
			width: 200,
			ellipsis: true,
		},
		{
			title: "Giới thiệu ngắn",
			dataIndex: "description",
			key: "description",
			ellipsis: true,
		},
		{
			title: "",
			key: "action",
			fixed: "right",
			width: 160,
			render: (_, record) => (
				<Space>
					<Button
						type="primary"
						size="small"
						onClick={() => handleSuggestEdit(record)}
					>
						Đề xuất chỉnh sửa
					</Button>
				</Space>
			),
		},
	];

	return (
		<div style={{ margin: "20px auto" }}>
			<Title level={2} style={{ textAlign: "center", marginBottom: 20 }}>
				Quản lý phòng khám, bệnh viện
			</Title>

			<SearchHeader
				provinces={provinces}
				districts={districts}
				selectedProvince={selectedProvince}
				selectedDistrict={selectedDistrict}
				searchText={searchText}
				loading={loadingRefer}
				handleProvinceChange={handleProvinceChange}
				handleDistrictChange={handleDistrictChange}
				setSearchText={handleChangeText}
				handleSearch={handleClinicsSearch}
			/>

			<Table
				rowKey="id"
				columns={columns}
				dataSource={clinicsRefer}
				loading={loadingRefer}
				pagination={{
					current: page,
					pageSize: limit,
					total,
					showSizeChanger: false,
					showTotal: (total) => `Tổng ${total} phòng khám`,
				}}
				onChange={handleTableChange}
				scroll={{ x: 1000 }}
			/>
		</div>
	);
}
