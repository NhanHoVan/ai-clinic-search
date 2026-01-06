import { useState, useEffect, useCallback } from "react";
import { Typography, message, Table, Button, Space, Tooltip } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";
import { useLocationPreference } from "@/hooks/use-location-preference";
import SearchHeader from "@/component/search-header.jsx";
import EditClinicModal from "@/component/edit-clinic-modal.jsx";
import { PlusOutlined } from "@ant-design/icons";
import { getStatusIcon, getStatusTooltip } from "@/utils/utils";
import {
	ExclamationCircleFilled,
	CheckCircleFilled,
	CheckCircleTwoTone,
} from "@ant-design/icons";

const { Title, Text } = Typography;

const IconMap = {
	ExclamationCircleFilled: (
		<ExclamationCircleFilled style={{ color: "#ff5716" }} />
	),
	CheckCircleFilled: <CheckCircleFilled style={{ color: "#52c41a" }} />,
	CheckCircleTwoTone: <CheckCircleTwoTone twoToneColor="#52c41a" />,
};

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

	// Model edit clinic
	const [openModel, setOpenModel] = useState(false);
	const [loadingModel, setLoadingModel] = useState(false);
	const [selectedClinic, setSelectedClinic] = useState(null);

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

	const getClinicDetail = useCallback(
		async (id) => {
			const data = await apiFetch("/clinic", {
				method: "GET",
				params: { id },
			});

			setSelectedClinic(data);
			return data;
		},
		[apiFetch]
	);

	const updateClinicDetail = useCallback(
		async (payload) => {
			if (!payload) {
				throw new Error("Missing required data");
			}

			return await apiFetch("/clinic/suggest-update", {
				body: {
					...payload,
				},
			});
		},
		[apiFetch, selectedClinic]
	);

	const createSuggestClinic = useCallback(
		async (payload) => {
			if (!payload) {
				throw new Error("Missing required data");
			}

			return await apiFetch("/clinic/suggest-create", {
				body: {
					...payload,
				},
			});
		},
		[apiFetch]
	);

	const handleSubmitClinic = async (data) => {
		try {
			setLoadingModel(true);

			if (selectedClinic) {
				await updateClinicDetail(data);
			} else {
				await createSuggestClinic(data);
			}
			message.success("Gửi đề xuất thành công");
			setOpenModel(false);
			setSelectedClinic(null);
			loadClinics({ page: page, search: searchText });
		} catch (e) {
			message.error("Có lỗi xảy ra, vui lòng thử lại");
		} finally {
			setLoadingModel(false);
		}
	};

	const handleSuggestEdit = async (record) => {
		try {
			setLoadingModel(true);

			await getClinicDetail(record.id);
			setOpenModel(true);
		} catch (err) {
			message.error("Không thể tải thông tin phòng khám");

			const clinic = clinicsRefer.find((item) => item.id === record.id);

			if (clinic) {
				setSelectedClinic(clinic);
				setOpenModel(true);
			}
		} finally {
			setLoadingModel(false);
		}
	};

	const columns = [
		{
			title: "ID",
			dataIndex: "id",
			key: "id",
			width: 80,
		},
		{
			title: "",
			key: "verify_status",
			width: 50,
			render: (_, record) => (
				<Space>
					<Tooltip title={getStatusTooltip(record.verify_status)}>
						{IconMap[getStatusIcon(record.verify_status)]}
					</Tooltip>
				</Space>
			),
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

			<Button
				type="primary"
				style={{ marginBottom: 20 }}
				icon={<PlusOutlined />}
				onClick={() => {
					setSelectedClinic(null);
					setOpenModel(true);
				}}
			>
				Đề xuất thêm phòng khám
			</Button>

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

			<EditClinicModal
				key={selectedClinic?.id ?? "create"}
				open={openModel}
				loading={loadingModel}
				clinic={selectedClinic}
				onCancel={() => {
					setOpenModel(false);
					setSelectedClinic(null);
				}}
				onSubmit={handleSubmitClinic}
				title={
					selectedClinic
						? "Đề xuất chỉnh sửa thông tin phòng khám"
						: "Đề xuất thêm phòng khám mới"
				}
				okeText={selectedClinic ? "Gửi đề xuất" : "Tạo đề xuất"}
				cancelText="Hủy"
				provinces={provinces}
			/>
		</div>
	);
}
