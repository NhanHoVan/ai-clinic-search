import { Modal, Form } from "antd";
import React from "react";
import EditClinicForm from "./form/edit-clinic-form";

export default function EditClinicModal({
	open,
	loading,
	clinic,
	onCancel,
	onSubmit,
	title,
	okeText,
	cancelText,
}) {
	const [form] = Form.useForm();

	React.useEffect(() => {
		if (clinic) {
			form.setFieldsValue(clinic);
		}
	}, [clinic, form]);

	return (
		<Modal
			title={<span style={{ fontSize: 20 }}>{title}</span>}
			open={open}
			onCancel={onCancel}
			onOk={() => form.submit()}
			confirmLoading={loading}
			okText={okeText}
			cancelText={cancelText}
			width={{
				xs: "90%",
				md: "80%",
				lg: "70%",
				xl: "60%",
			}}
			maskClosable={false}
			style={{ top: 50 }}
			styles={{
				header: {
					marginBottom: 25,
				},
			}}
		>
			<EditClinicForm form={form} onFinish={onSubmit} />
		</Modal>
	);
}
