import { Form, Input } from "antd";

const { TextArea } = Input;

export default function EditClinicForm({ form, onFinish }) {
	return (
		<Form
			form={form}
			// layout="vertical"
			onFinish={onFinish}
			labelCol={{ flex: "200px" }}
			labelAlign="left"
			labelWrap
			wrapperCol={{ flex: 1 }}
		>
			<Form.Item label="ID" name="id">
				<Input disabled />
			</Form.Item>

			<Form.Item label="Place ID" name="place_id">
				<Input disabled />
			</Form.Item>

			<Form.Item
				label="Tên phòng khám"
				name="name"
				rules={[
					{ required: true, message: "Vui lòng nhập tên phòng khám" },
				]}
			>
				<Input />
			</Form.Item>

			<Form.Item
				label="Địa chỉ"
				name="address"
				rules={[{ required: true, message: "Vui lòng nhập địa chỉ" }]}
			>
				<Input />
			</Form.Item>

			<Form.Item label="Số điện thoại" name="phone">
				<Input />
			</Form.Item>

			<Form.Item
				label="Email"
				name="email"
				rules={[{ type: "email", message: "Email không hợp lệ" }]}
			>
				<Input />
			</Form.Item>

			<Form.Item label="Website" name="website">
				<Input />
			</Form.Item>

			<Form.Item
				label="Mô tả"
				name="description"
				rules={[{ max: 500, message: "Tối đa 500 ký tự" }]}
			>
				<TextArea rows={4} maxLength={500} showCount />
			</Form.Item>

			<Form.Item label="Ảnh đại diện (URL)" name="image">
				<Input />
			</Form.Item>
		</Form>
	);
}
