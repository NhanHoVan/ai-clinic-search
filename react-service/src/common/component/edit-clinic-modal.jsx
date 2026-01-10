import { Modal, Form } from "antd";
import React from "react";

export default function EditClinicModal({
	open,
	loading,
	clinic,
	onCancel,
	onSubmit,
	title,
	okeText,
	cancelText,
	renderForm,
}) {
	const [form] = Form.useForm();

	React.useEffect(() => {
		if (clinic) {
			form.setFieldsValue(clinic);
		} else {
			form.resetFields();
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
				lg: "60%",
				xl: "50%",
			}}
			maskClosable={false}
			style={{ top: 50 }}
		>
			{renderForm && renderForm(form, onSubmit)}
		</Modal>
	);
}

