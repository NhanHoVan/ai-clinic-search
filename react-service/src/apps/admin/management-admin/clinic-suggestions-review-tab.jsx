import { useState, useCallback, useEffect, useRef } from "react";
import { Table, Tag, Button, message } from "antd";
import EditClinicModal from "@/component/edit-clinic-modal.jsx";
import ReviewSuggestionForm from "@/component/form/review-suggestion-clinic-form.jsx";
import { useWordPress } from "@/hooks/use-wordpress";

export default function ClinicSuggestionReviewTab({ specialties }) {
	const { apiFetch } = useWordPress();

	const [clinicSuggestions, setClinicSuggestions] = useState([]);
	const [loading, setLoading] = useState(false);
	const [page, setPage] = useState(1);
	const [limit] = useState(20);
	const [total, setTotal] = useState(0);

	const [openModal, setOpenModal] = useState(false);
	const [loadingModal, setLoadingModal] = useState(false);
	const [selectedSuggestionClinic, setSelectedSuggestionClinic] =
		useState(null);

	const fetchClinicSuggestions = useCallback(
		async ({ page }) => {
			const data = await apiFetch("/clinic-suggestions", {
				body: {
					page,
					limit,
				},
			});

			setClinicSuggestions(data.clinics || []);
			setTotal(data.total || 0);
			setPage(page);
		},
		[apiFetch],
	);

	const loadClinics = async ({ page = 1 }) => {
		setLoading(true);
		try {
			await fetchClinicSuggestions({ page });
		} catch (e) {
			message.error(e?.message || "Không thể tải danh sách gợi ý");
		} finally {
			setLoading(false);
		}
	};

	const handleTableChange = (pagination) => {
		loadClinics({
			page: pagination.current,
		});
	};

	const didFetchRef = useRef(false);
	useEffect(() => {
		if (didFetchRef.current) return;
		didFetchRef.current = true;
		loadClinics({ page: 1 });
	}, []);

	const reviewClinicSuggestion = useCallback(
		async (id, payload) => {
			return apiFetch("/clinic-suggestions/review", {
				body: {
					id,
					...payload,
				},
			});
		},
		[apiFetch],
	);

	const handleSubmitReview = async (data) => {
		try {
			setLoadingModal(true);
			if (data.status === "PENDING") {
				message.error("Vui lòng chọn Duyệt hoặc Từ chối");
				return;
			}

			await reviewClinicSuggestion(selectedSuggestionClinic.id, data);

			message.success("Hợp nhất thông tin thành công");
			setOpenModal(false);
			setSelectedSuggestionClinic(null);
			loadClinics({ page: page });
		} catch (e) {
			message.error("Có lỗi xảy ra, vui lòng thử lại sau!");
		} finally {
			setLoadingModal(false);
		}
	};

	const handleMerge = (record) => {
		setSelectedSuggestionClinic(record);
		setOpenModal(true);
	};

	const columns = [
		{ title: "ID", dataIndex: "id", key: "id", width: 80 },

		{
			title: "Loại",
			dataIndex: "suggestion_type",
			key: "suggestion_type",
			width: 120,
			render: (type) =>
				type === "CREATE" ? (
					<Tag color="blue">Tạo mới</Tag>
				) : (
					<Tag color="gold">Chỉnh sửa</Tag>
				),
		},

		{
			title: "Phòng khám",
			dataIndex: "clinic_id",
			key: "clinic_id",
			width: 130,
			render: (clinicId) =>
				clinicId ? <Tag color="green">ID: {clinicId}</Tag> : null,
		},

		{
			title: "Người đề xuất",
			key: "suggested_by",
			width: 180,
			render: (_, r) => (
				<>
					<Tag
						color={r.suggested_by === "OWNER" ? "cyan" : "default"}
					>
						{r.suggested_by === "OWNER" ? "Chủ PK" : "Người dùng"}
					</Tag>
					{r.user_name && <div>({r.user_name})</div>}
				</>
			),
		},

		{
			title: "Nội dung",
			dataIndex: "suggested_fields",
			key: "suggested_fields",
			ellipsis: true,
			render: (fields) => {
				const keys = Object.keys(fields || {});
				return keys.length
					? `${keys.length} trường (${keys
							.slice(0, 3)
							.join(", ")}...)`
					: "—";
			},
		},

		{
			title: "Ngày tạo",
			dataIndex: "created_at",
			key: "created_at",
			width: 200,
			ellipsis: true,
		},

		{
			title: "Trạng thái",
			dataIndex: "status",
			key: "status",
			width: 100,
			render: (status) => {
				const map = {
					PENDING: ["orange", "Chờ duyệt"],
					APPROVED: ["green", "Đã duyệt"],
					REJECTED: ["default", "Từ chối"],
				};
				return <Tag color={map[status][0]}>{map[status][1]}</Tag>;
			},
		},

		{
			title: "Nhận xét",
			dataIndex: "review_note",
			key: "review_note",
			width: 250,
			ellipsis: true,
		},

		{
			title: "Hành động",
			key: "action",
			fixed: "right",
			width: 120,
			render: (_, record) => (
				<Button
					type="primary"
					size="small"
					disabled={record.status !== "PENDING"}
					onClick={() => handleMerge(record)}
				>
					Review
				</Button>
			),
		},
	];

	return (
		<div style={{ margin: "20px auto" }}>
			<Table
				rowKey="id"
				columns={columns}
				dataSource={clinicSuggestions}
				loading={loading}
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
				key={selectedSuggestionClinic?.id}
				open={openModal}
				loading={loadingModal}
				clinic={selectedSuggestionClinic}
				onCancel={() => {
					setOpenModal(false);
					setSelectedSuggestionClinic(null);
				}}
				onSubmit={handleSubmitReview}
				title="Xem xét đề xuất phòng khám"
				okeText="Duyệt đề xuất"
				cancelText="Hủy"
				renderForm={(form, onFinish) => (
					<ReviewSuggestionForm
						form={form}
						onFinish={onFinish}
						suggestion={selectedSuggestionClinic}
					/>
				)}
			/>
		</div>
	);
}
