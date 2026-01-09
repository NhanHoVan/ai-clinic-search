import { Form, Select, Tag, Descriptions, Input } from "antd";

const { TextArea } = Input;

const STATUS_OPTIONS = [
	{ label: "Chờ duyệt", value: "PENDING", disabled: true },
	{ label: "Duyệt", value: "APPROVED" },
	{ label: "Từ chối", value: "REJECTED" },
];

export default function ReviewSuggestionForm({ form, onFinish, suggestion }) {
	if (!suggestion) return null;

	const {
		id,
		suggestion_type,
		clinic_id,
		user_id,
		suggested_by,
		status,
		created_at,
		suggested_fields,
	} = suggestion;

	return (
		<Form form={form} onFinish={onFinish} layout="vertical">
			<Descriptions
				title=""
				bordered
				size="small"
				column={1}
				style={{ marginBottom: 16 }}
			>
				<Descriptions.Item label="ID">{id}</Descriptions.Item>

				<Descriptions.Item label="Loại đề xuất">
					{suggestion_type === "CREATE" ? (
						<Tag color="blue">Tạo mới</Tag>
					) : (
						<Tag color="gold">Chỉnh sửa</Tag>
					)}
				</Descriptions.Item>

				<Descriptions.Item label="Phòng khám">
					{clinic_id ? (
						`#${clinic_id}`
					) : (
						<Tag color="green">Phòng khám mới</Tag>
					)}
				</Descriptions.Item>

				<Descriptions.Item label="Người đề xuất">
					<Tag color={suggested_by === "OWNER" ? "cyan" : "default"}>
						{suggested_by === "OWNER"
							? "Chủ phòng khám"
							: "Người dùng"}
					</Tag>
					{user_id && (
						<span style={{ marginLeft: 8 }}>User #{user_id}</span>
					)}
				</Descriptions.Item>

				<Descriptions.Item label="Thời gian tạo">
					{created_at}
				</Descriptions.Item>
			</Descriptions>

			<Form.Item label="Nội dung đề xuất">
				<Descriptions bordered size="small" column={1}>
					{Object.entries(suggested_fields || {}).map(
						([key, value]) => (
							<Descriptions.Item key={key} label={key}>
								{Array.isArray(value)
									? value.join(", ")
									: typeof value === "object"
									? JSON.stringify(value, null, 2)
									: String(value)}
							</Descriptions.Item>
						)
					)}
				</Descriptions>
			</Form.Item>

			<Form.Item
				label="Trạng thái"
				name="status"
				initialValue={status}
				rules={[
					{ required: true, message: "Vui lòng chọn trạng thái" },
				]}
			>
				<Select
					options={STATUS_OPTIONS}
					disabled={status !== "PENDING"}
				/>
			</Form.Item>

			<Form.Item
				label="Ghi chú review"
				name="review_note"
				rules={[
					{ required: true, message: "Vui lòng nhập ghi chú" },
					{ max: 500, message: "Tối đa 500 ký tự" },
				]}
			>
				<TextArea
					rows={4}
					maxLength={500}
					showCount
					placeholder="Nhập lý do duyệt / từ chối"
				/>
			</Form.Item>
		</Form>
	);
}
